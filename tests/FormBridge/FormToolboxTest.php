<?php

declare(strict_types=1);

namespace App\Tests\FormBridge;

use App\Entity\Booking;
use App\Tests\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\Agent\DraftLog;
use FormBridge\Agent\FormToolbox;
use FormBridge\FormToolRegistry;
use FormBridge\FormToolRunner;
use FormBridge\FormToolSchemas;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\Exception\ToolNotFoundException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A Symfony AI agent gets the forms as tools: it can fill them in, the form answers, nothing is saved.
 */
final class FormToolboxTest extends KernelTestCase
{
    use ResetsDatabase;

    private DraftLog $drafts;
    private FormToolbox $toolbox;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        self::resetDatabase();

        $this->drafts = new DraftLog();
        $this->toolbox = new FormToolbox(
            $container->get(FormToolRegistry::class),
            $container->get(FormToolRunner::class),
            $container->get(FormToolSchemas::class),
            $this->drafts,
        );
    }

    public function testEveryFormIsAToolWithParametersReadFromTheForm(): void
    {
        $tools = [];
        foreach ($this->toolbox->getTools() as $tool) {
            $tools[$tool->getName()] = $tool;
        }

        self::assertSame(['cancel_booking', 'create_booking', 'reschedule_booking'], array_keys($tools));
        self::assertStringContainsString('fri-19 = "Friday 19:00 Sourdough basics', $tools['create_booking']->getParameters()['properties']['session']['description']);
        self::assertTrue($tools['create_booking']->isReadOnly());
    }

    public function testThereIsNoToolForSaving(): void
    {
        $this->expectException(ToolNotFoundException::class);

        $this->toolbox->execute(new ToolCall('1', 'create_booking_submit', ['session' => 'sat-10', 'seats' => 1]));
    }

    public function testCallingAToolFillsTheFormInAndReturnsTheFormsOwnAnswer(): void
    {
        $result = $this->toolbox->execute(new ToolCall('1', 'create_booking', [
            'session' => 'fri-19', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com',
        ]));

        self::assertStringContainsString('Friday 19:00 Sourdough basics has only 1 seat left, and you asked for 2.', $result->getResult());
        self::assertCount(1, $this->drafts->all());
        self::assertFalse($this->drafts->all()[0]->outcome->valid);
        self::assertSame(4, $this->countBookings());
    }

    public function testAnAgentCorrectsItselfFromTheFormsAnswer(): void
    {
        // A model that first asks for Friday, reads the form's answer, then tries Saturday.
        $platform = MockPlatformFactory::createPlatform(static function ($model, array $payload) {
            $toolAnswers = array_values(array_filter($payload['messages'], static fn (array $message): bool => 'tool' === $message['role']));
            $booking = ['seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com'];

            return match (\count($toolAnswers)) {
                0 => new ToolCallResult([new ToolCall('1', 'create_booking', ['session' => 'fri-19'] + $booking)]),
                1 => str_contains($toolAnswers[0]['content'], 'sat-10 = "Saturday')
                    ? new ToolCallResult([new ToolCall('2', 'create_booking', ['session' => 'sat-10'] + $booking)])
                    : 'The form did not offer an alternative.',
                default => 'Friday is full, so I filled the form in for Saturday. Please check it and press Book.',
            };
        });
        $agent = new Agent($platform, 'test-model', toolbox: $this->toolbox);

        $reply = $agent->call(new MessageBag(Message::ofUser('Two seats on Friday for John, or the next free day.')))->asText();

        self::assertStringContainsString('Saturday', $reply);
        self::assertCount(2, $this->drafts->all());
        self::assertTrue($this->drafts->last()?->outcome->valid);
        self::assertSame(4, $this->countBookings(), 'An agent can fill forms in, but it cannot save them.');
    }

    private function countBookings(): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(Booking::class)->count([]);
    }
}
