<?php

declare(strict_types=1);

namespace App\Tests\FormBridge;

use App\Tests\ResetsDatabase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DebugFormToolsCommandTest extends KernelTestCase
{
    use ResetsDatabase;

    private CommandTester $command;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $this->command = new CommandTester((new Application(self::$kernel))->find('debug:form-tools'));
    }

    public function testItListsEveryFormTool(): void
    {
        $this->command->execute([]);

        $this->command->assertCommandIsSuccessful();
        $output = $this->command->getDisplay();
        foreach (['create_booking', 'reschedule_booking_submit', 'App\Form\CancelBookingType', 'Move booking'] as $expected) {
            self::assertStringContainsString($expected, $output);
        }
    }

    public function testItShowsWhatAnAgentIsToldAboutOneTool(): void
    {
        $this->command->execute(['name' => 'create_booking']);

        $this->command->assertCommandIsSuccessful();
        $output = $this->command->getDisplay();
        self::assertStringContainsString('Book seats for one person in a workshop session.', $output);
        self::assertStringContainsString('"additionalProperties": false', $output);
        self::assertStringContainsString('fri-19 = \\"Friday 19:00 Sourdough basics (1 seat left, €25 per seat)\\"', $output);
    }

    public function testAnUnknownToolIsAnError(): void
    {
        self::assertSame(1, $this->command->execute(['name' => 'delete_everything']));
        self::assertStringContainsString('No form tool is named "delete_everything".', $this->command->getDisplay());
    }
}
