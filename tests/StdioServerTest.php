<?php

declare(strict_types=1);

namespace App\Tests;

use App\Booking\BookingRequest;
use App\Booking\CreateBookingHandler;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The STDIO server is one process for a whole conversation; it must not answer from what it
 * loaded for an earlier request.
 */
final class StdioServerTest extends KernelTestCase
{
    use ResetsDatabase;

    /** @var resource */
    private $server;
    /** @var array<int, resource> */
    private array $pipes = [];
    private int $requestId = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();

        $server = proc_open(
            [\PHP_BINARY, 'bin/console', 'mcp:server', '--env=test'],
            [['pipe', 'r'], ['pipe', 'w'], ['file', '/dev/null', 'w']],
            $this->pipes,
            \dirname(__DIR__),
        );
        self::assertIsResource($server);
        $this->server = $server;
        stream_set_timeout($this->pipes[1], 20);

        $this->request('initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '0']]);
        fwrite($this->pipes[0], json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])."\n");
    }

    protected function tearDown(): void
    {
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        proc_terminate($this->server);
        proc_close($this->server);

        parent::tearDown();
    }

    public function testABookingMadeElsewhereShowsUpInTheSameConversation(): void
    {
        $before = $this->formFor('reschedule_booking', ['booking' => '4', 'newSession' => 'sat-10']);
        self::assertSame(4, preg_match_all('/<option value="\d+"/', $before), 'Four bookings are offered at first.');
        self::assertStringContainsString('Sunday 14:00 Croissants (7 seats left)', $before);

        // Another process takes a booking for two on Sunday.
        $container = self::getContainer();
        $request = new BookingRequest();
        $request->session = $container->get(EntityManagerInterface::class)->find(Session::class, 'sun-14');
        $request->seats = 2;
        $request->name = 'John Doe';
        $request->email = 'john@example.com';
        $container->get(CreateBookingHandler::class)($request);

        $after = $this->formFor('reschedule_booking', ['booking' => '5', 'newSession' => 'sat-10']);
        self::assertStringContainsString('#5 John Doe, 2 seats in Sunday 14:00 Croissants', $after);
        self::assertStringContainsString('Sunday 14:00 Croissants (5 seats left)', $after);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function formFor(string $tool, array $arguments): string
    {
        return $this->request('tools/call', ['name' => $tool, 'arguments' => $arguments])['_meta']['formbridge/html'];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function request(string $method, array $params): array
    {
        $id = ++$this->requestId;
        fwrite($this->pipes[0], json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params], \JSON_THROW_ON_ERROR)."\n");

        while (false !== $line = fgets($this->pipes[1])) {
            $answer = json_decode($line, true);
            if (\is_array($answer) && ($answer['id'] ?? null) === $id) {
                self::assertArrayHasKey('result', $answer, $method.' failed: '.$line);

                return $answer['result'];
            }
        }

        self::fail('The server did not answer '.$method.'.');
    }
}
