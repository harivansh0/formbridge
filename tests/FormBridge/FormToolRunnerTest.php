<?php

declare(strict_types=1);

namespace App\Tests\FormBridge;

use App\Entity\Booking;
use App\Tests\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\FormToolRunner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Drafting validates and never saves; submitting validates again and then saves.
 */
final class FormToolRunnerTest extends KernelTestCase
{
    use ResetsDatabase;

    private const PRIYA = ['session' => 'sat-10', 'seats' => 2, 'name' => 'Priya Nair', 'email' => 'priya@example.com'];

    private FormToolRunner $runner;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $this->runner = self::getContainer()->get(FormToolRunner::class);
    }

    public function testADraftIsValidatedByTheApplicationAndReturnsTheAlternatives(): void
    {
        $outcome = $this->runner->draft('create_booking', ['session' => 'fri-19'] + self::PRIYA);

        self::assertFalse($outcome->valid);
        self::assertFalse($outcome->saved);
        self::assertCount(1, $outcome->errors);
        self::assertSame('session', $outcome->errors[0]['field']);
        self::assertSame('Friday 19:00 Sourdough basics has only 1 seat left, and you asked for 2.', $outcome->errors[0]['message']);
        self::assertSame(['fri-19', 'sat-10', 'sun-14'], array_column($outcome->errors[0]['choices'], 'value'));
    }

    public function testAValidDraftSavesNothing(): void
    {
        $outcome = $this->runner->draft('create_booking', self::PRIYA);

        self::assertTrue($outcome->valid);
        self::assertFalse($outcome->saved);
        self::assertSame(4, $this->countBookings());
    }

    public function testAPartlyFilledDraftSaysWhatIsMissing(): void
    {
        $outcome = $this->runner->draft('create_booking', ['session' => 'sun-14']);

        self::assertFalse($outcome->valid);
        self::assertEqualsCanonicalizing(['seats', 'name', 'email'], array_column($outcome->errors, 'field'));
    }

    public function testSubmittingRunsTheApplicationsHandler(): void
    {
        // Submitted the way a browser posts a form: text values.
        $outcome = $this->runner->submit('create_booking', ['seats' => '2'] + self::PRIYA);

        self::assertTrue($outcome->saved);
        self::assertSame('Booking #5: 2 seats in Saturday 10:00 Sourdough basics for Priya Nair, €50 in total.', $outcome->result);
        self::assertSame(5, $this->countBookings());
    }

    public function testADraftThatWasValidIsRefusedWhenTheSeatsAreGoneBySubmitTime(): void
    {
        self::assertTrue($this->runner->draft('create_booking', self::PRIYA)->valid);

        // Someone else books three of Saturday's four free seats in the meantime.
        $this->runner->submit('create_booking', ['seats' => '3', 'name' => 'Sam Okoro', 'email' => 'sam@example.com'] + self::PRIYA);
        $outcome = $this->runner->submit('create_booking', self::PRIYA);

        self::assertFalse($outcome->saved);
        self::assertSame('Saturday 10:00 Sourdough basics has only 1 seat left, and you asked for 2.', $outcome->errors[0]['message']);
        self::assertSame(5, $this->countBookings());
    }

    private function countBookings(): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(Booking::class)->count([]);
    }
}
