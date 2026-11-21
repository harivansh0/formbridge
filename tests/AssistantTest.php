<?php

declare(strict_types=1);

namespace App\Tests;

use App\Assistant\AssistantPlatform;
use App\Assistant\OfflineResponder;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The assistant screen. Tests run without a provider key, so the offline responder stands in for
 * the model; the agent, its tool calls and the forms are the real ones.
 */
final class AssistantTest extends WebTestCase
{
    use ResetsDatabase;

    public function testTheScreenShowsTheAssistantNextToTheBookings(): void
    {
        $client = self::createClient();
        self::resetDatabase();

        $page = $client->request('GET', '/assistant');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.pane.chat h1', 'Booking assistant');
        self::assertSame('/', $page->filter('iframe#site')->attr('src'));
        self::assertCount(4, $page->filter('#requests button'));
    }

    public function testTheAssistantFillsTheFormInAndReportsWhatTheFormSays(): void
    {
        $client = self::createClient();
        self::resetDatabase();

        $answer = $this->ask($client, 'Book 2 seats on Friday for John Doe, john@example.com');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Friday 19:00 Sourdough basics has only 1 seat left', $answer['reply']);
        self::assertSame('create_booking', $answer['form']['tool']);
        self::assertFalse($answer['form']['result']['structuredContent']['valid']);
        self::assertStringContainsString('data-call="create_booking_submit"', $answer['form']['result']['_meta']['formbridge/html']);
    }

    public function testASuggestionAboutAnExistingBookingFindsThatBooking(): void
    {
        $client = self::createClient();
        self::resetDatabase();

        $answer = $this->ask($client, "Cancel Omar Haddad's booking, he is ill");

        self::assertSame('cancel_booking', $answer['form']['tool']);
        self::assertSame(['booking' => '4', 'reason' => 'He is ill.'], $answer['form']['arguments']);
        self::assertTrue($answer['form']['result']['structuredContent']['valid']);
        self::assertFalse($answer['form']['result']['structuredContent']['saved']);
    }

    public function testOfflineTheAssistantSaysWhatItCanDo(): void
    {
        $client = self::createClient();

        $answer = $this->ask($client, 'What is the weather like?');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('running offline', $answer['reply']);
        self::assertNull($answer['form']);
    }

    public function testInProductionThereAreNoCannedReplies(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $provider = self::createStub(PlatformInterface::class);
        $offline = $container->get(OfflineResponder::class);

        self::assertTrue((new AssistantPlatform($provider, $offline, '', 'dev'))->isAvailable());
        self::assertTrue((new AssistantPlatform($provider, $offline, 'a-key', 'prod'))->isAvailable());

        $withoutKey = new AssistantPlatform($provider, $offline, '', 'prod');
        self::assertFalse($withoutKey->isAvailable());
        $this->expectException(\LogicException::class);
        $withoutKey->invoke('any-model', 'Hello');
    }

    public function testAnEmptyQuestionIsRefused(): void
    {
        $client = self::createClient();

        $this->ask($client, '');

        self::assertResponseStatusCodeSame(422);
    }

    public function testAQuestionThatDoesNotComeFromTheScreenIsRefused(): void
    {
        $client = self::createClient();

        $client->jsonRequest('POST', '/assistant/ask', ['message' => 'Book 2 seats on Friday for John Doe, john@example.com']);
        self::assertResponseStatusCodeSame(403);

        // A cross-site HTML form post cannot be JSON.
        $client->request('POST', '/assistant/ask', ['message' => 'Book 2 seats on Friday for John Doe, john@example.com']);
        self::assertResponseStatusCodeSame(415);
    }

    public function testQuestionsAreRateLimitedPerClient(): void
    {
        $client = self::createClient();
        // The test limiter counts in memory, so the kernel must stay up between requests.
        $client->disableReboot();
        $token = $this->screenToken($client);

        for ($i = 0; $i < 3; ++$i) {
            $client->jsonRequest('POST', '/assistant/ask', ['message' => 'What is the weather like?'], ['HTTP_X_CSRF_TOKEN' => $token]);
            self::assertResponseIsSuccessful();
        }

        $client->jsonRequest('POST', '/assistant/ask', ['message' => 'What is the weather like?'], ['HTTP_X_CSRF_TOKEN' => $token]);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }

    /**
     * @return array<string, mixed>
     */
    private function ask(KernelBrowser $client, string $message): array
    {
        $client->jsonRequest('POST', '/assistant/ask', ['message' => $message], ['HTTP_X_CSRF_TOKEN' => $this->screenToken($client)]);

        return json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function screenToken(KernelBrowser $client): string
    {
        $screen = (string) $client->request('GET', '/assistant')->html();
        self::assertSame(1, preg_match('/\'X-CSRF-Token\': "([^"]+)"/', $screen, $token));

        return json_decode('"'.$token[1].'"');
    }
}
