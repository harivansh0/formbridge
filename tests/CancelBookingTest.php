<?php

declare(strict_types=1);

namespace App\Tests;

use App\Booking\CancelBookingHandler;
use App\Booking\CancelRequest;
use App\Entity\Booking;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\FormToolRunner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class CancelBookingTest extends KernelTestCase
{
    use ResetsDatabase;

    private EntityManagerInterface $entityManager;
    private FormToolRunner $runner;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->runner = self::getContainer()->get(FormToolRunner::class);
    }

    public function testCancellingReleasesTheSeats(): void
    {
        $outcome = $this->runner->submit('cancel_booking', ['booking' => '1', 'reason' => 'Customer is ill.']);

        self::assertTrue($outcome->saved);
        self::assertSame('Booking #1 for Tom Reid is cancelled. 3 seats in Friday 19:00 Sourdough basics are free again.', $outcome->result);

        $this->entityManager->clear();
        $booking = $this->entityManager->find(Booking::class, 1);
        self::assertTrue($booking->isCancelled());
        self::assertSame('Customer is ill.', $booking->getCancellationReason());
        self::assertNotNull($booking->getCancelledAt());
        self::assertSame(4, $this->entityManager->find(Session::class, 'fri-19')->getSeatsLeft());
    }

    public function testACancelledBookingIsNoLongerOfferedForMovingOrCancelling(): void
    {
        $this->runner->submit('cancel_booking', ['booking' => '1']);

        foreach (['cancel_booking' => ['booking' => '1'], 'reschedule_booking' => ['booking' => '1', 'newSession' => 'sun-14']] as $tool => $values) {
            $outcome = $this->runner->draft($tool, $values);

            self::assertFalse($outcome->valid);
            self::assertSame('booking', $outcome->errors[0]['field']);
            self::assertSame(['2', '3', '4'], array_column($outcome->errors[0]['choices'], 'value'));
        }
    }

    public function testTheHandlerRefusesABookingThatWasCancelledInTheMeantime(): void
    {
        $handler = self::getContainer()->get(CancelBookingHandler::class);
        $request = new CancelRequest();
        $request->booking = $this->entityManager->find(Booking::class, 1);

        $handler($request);

        try {
            $handler($request);
            self::fail('A booking was cancelled twice.');
        } catch (ValidationFailedException $refused) {
            self::assertSame('Booking #1 is already cancelled.', (string) $refused->getViolations()[0]->getMessage());
        }
    }
}
