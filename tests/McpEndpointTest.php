<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The exchange as an MCP client sees it over HTTP.
 */
final class McpEndpointTest extends WebTestCase
{
    use ResetsDatabase;

    private KernelBrowser $client;
    private ?string $sessionId = null;
    private int $requestId = 0;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::resetDatabase();

        $this->rpc('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => ['extensions' => ['io.modelcontextprotocol/ui' => ['mimeTypes' => ['text/html;profile=mcp-app']]]],
            'clientInfo' => ['name' => 'test', 'version' => '0'],
        ]);
        $this->sessionId = $this->client->getResponse()->headers->get('Mcp-Session-Id');
        $this->rpc('notifications/initialized', [], notification: true);
    }

    public function testEachFormGetsAToolForTheModelAndASubmitToolForTheFormOnly(): void
    {
        $tools = array_column($this->rpc('tools/list', [])['tools'], null, 'name');

        self::assertSame(
            ['cancel_booking', 'cancel_booking_submit', 'create_booking', 'create_booking_submit', 'reschedule_booking', 'reschedule_booking_submit'],
            array_keys($tools),
        );
        self::assertSame(['model', 'app'], $tools['create_booking']['_meta']['ui']['visibility']);
        self::assertSame(['app'], $tools['create_booking_submit']['_meta']['ui']['visibility']);
        self::assertSame('ui://formbridge-form', $tools['create_booking']['_meta']['ui']['resourceUri']);
    }

    public function testADraftIsAnsweredWithTextForTheModelDataForProgramsAndTheFormForThePerson(): void
    {
        $result = $this->call('create_booking', ['session' => 'fri-19', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com']);

        self::assertFalse($result['isError']);
        self::assertStringContainsString('has only 1 seat left', $result['content'][0]['text']);
        self::assertFalse($result['structuredContent']['valid']);
        self::assertFalse($result['structuredContent']['saved']);
        self::assertSame('session', $result['structuredContent']['errors'][0]['field']);
        self::assertStringContainsString('data-call="create_booking_submit"', $result['_meta']['formbridge/html']);
        self::assertStringContainsString('has only 1 seat left', $result['_meta']['formbridge/html']);
    }

    public function testTheSubmitTokenIsOnlyInTheFormAndNeverInWhatTheModelReads(): void
    {
        $result = $this->call('create_booking', ['session' => 'sat-10', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com']);
        $token = $this->tokenIn($result);

        self::assertStringNotContainsString($token, json_encode([$result['content'], $result['structuredContent']], \JSON_THROW_ON_ERROR));
    }

    public function testSavingWithoutTheFormsTokenIsRefused(): void
    {
        $result = $this->call('create_booking_submit', ['session' => 'sat-10', 'seats' => '2', 'name' => 'Mallory', 'email' => 'mallory@example.com']);

        self::assertTrue($result['isError']);
        self::assertSame(4, $this->countBookings());
    }

    public function testThePersonSubmittingTheFormSavesTheBookingOnce(): void
    {
        $draft = $this->call('create_booking', ['session' => 'sat-10', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com']);
        $fields = ['_formbridge_token' => $this->tokenIn($draft), 'session' => 'sat-10', 'seats' => '2', 'name' => 'John Doe', 'email' => 'john@example.com', 'notes' => ''];

        $saved = $this->call('create_booking_submit', $fields);
        self::assertTrue($saved['structuredContent']['saved']);
        self::assertStringContainsString('Booking #5', $saved['structuredContent']['result']);
        self::assertSame(5, $this->countBookings());

        $again = $this->call('create_booking_submit', $fields);
        self::assertTrue($again['isError']);
        self::assertSame(5, $this->countBookings());
    }

    public function testOverMcpTheFormAnswersInTheSameWordsAsTheWebsite(): void
    {
        $values = ['session' => 'fri-19', 'seats' => 9, 'name' => '', 'email' => 'not-an-email'];

        $overMcp = array_column($this->call('create_booking', $values)['structuredContent']['errors'], 'message');

        $page = $this->client->request('GET', '/book');
        $form = $page->selectButton('Book')->form();
        foreach ($values as $field => $value) {
            $form['booking['.$field.']'] = (string) $value;
        }
        $onTheWebsite = $this->client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost'])->filter('form.booking ul li')->each(static fn ($item): string => $item->text());

        self::assertCount(4, $overMcp);
        self::assertEqualsCanonicalizing($onTheWebsite, $overMcp);
    }

    public function testASessionTheSchemaDidNotListStillReachesTheForm(): void
    {
        $result = $this->call('create_booking', ['session' => 'mon-09', 'seats' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']);

        $error = $result['structuredContent']['errors'][0];
        self::assertSame('session', $error['field']);
        self::assertSame('The selected choice is invalid.', $error['message']);
        self::assertSame(['fri-19', 'sat-10', 'sun-14'], array_column($error['choices'], 'value'));
    }

    public function testSavingThroughTheFormsToolClosesTheReviewLink(): void
    {
        $draft = $this->call('create_booking', ['session' => 'sat-10', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com']);
        $reviewPath = (string) parse_url($draft['structuredContent']['reviewUrl'], \PHP_URL_PATH);

        $this->client->request('GET', $reviewPath);
        self::assertResponseIsSuccessful();

        $saved = $this->call('create_booking_submit', ['_formbridge_token' => $this->tokenIn($draft), 'session' => 'sat-10', 'seats' => '2', 'name' => 'John Doe', 'email' => 'john@example.com', 'notes' => '']);
        self::assertTrue($saved['structuredContent']['saved']);

        $this->client->request('GET', $reviewPath);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnUnknownFieldIsRejectedBeforeTheFormRuns(): void
    {
        $message = ['jsonrpc' => '2.0', 'id' => ++$this->requestId, 'method' => 'tools/call', 'params' => ['name' => 'create_booking', 'arguments' => ['seats' => 1, 'discount' => '50%']]];
        $this->client->request('POST', '/mcp', server: [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream',
            'HTTP_MCP_SESSION_ID' => $this->sessionId, 'HTTP_MCP_PROTOCOL_VERSION' => '2025-11-25',
        ], content: json_encode($message, \JSON_THROW_ON_ERROR));

        self::assertStringContainsString('Invalid parameters for tool', (string) $this->client->getInternalResponse()->getContent());
        self::assertSame(4, $this->countBookings());
    }

    public function testTheFormScreenIsServedAsAnMcpApp(): void
    {
        $content = $this->rpc('resources/read', ['uri' => 'ui://formbridge-form'])['contents'][0];

        self::assertSame('text/html;profile=mcp-app', $content['mimeType']);
        self::assertStringContainsString('ui/initialize', $content['text']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function call(string $tool, array $arguments): array
    {
        return $this->rpc('tools/call', ['name' => $tool, 'arguments' => $arguments]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params, bool $notification = false): array
    {
        $message = ['jsonrpc' => '2.0', 'method' => $method, 'params' => [] === $params ? new \stdClass() : $params];
        if (!$notification) {
            $message['id'] = ++$this->requestId;
        }

        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if (null !== $this->sessionId) {
            $headers['HTTP_MCP_SESSION_ID'] = $this->sessionId;
            $headers['HTTP_MCP_PROTOCOL_VERSION'] = '2025-11-25';
        }
        $this->client->request('POST', '/mcp', server: $headers, content: json_encode($message, \JSON_THROW_ON_ERROR));
        if ($notification) {
            return [];
        }

        // One JSON document, either as the body or as a server-sent event.
        $body = (string) $this->client->getInternalResponse()->getContent();
        foreach (preg_split('/\R/', $body) as $line) {
            $line = preg_replace('/^data:\s*/', '', $line);
            if (!str_starts_with($line, '{')) {
                continue;
            }
            $answer = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            if (($answer['id'] ?? null) === $this->requestId) {
                self::assertArrayNotHasKey('error', $answer, $method.' was rejected: '.json_encode($answer['error'] ?? null));

                return $answer['result'];
            }
        }

        self::fail(\sprintf('No answer to "%s" (HTTP %d): %s', $method, $this->client->getResponse()->getStatusCode(), $body));
    }

    /**
     * @param array<string, mixed> $result
     */
    private function tokenIn(array $result): string
    {
        self::assertSame(1, preg_match('/name="_formbridge_token" value="([a-f0-9]{32})"/', $result['_meta']['formbridge/html'], $match));

        return $match[1];
    }

    private function countBookings(): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(Booking::class)->count([]);
    }
}
