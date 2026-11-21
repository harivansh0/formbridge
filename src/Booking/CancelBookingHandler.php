<?php

declare(strict_types=1);

namespace App\Booking;

use App\Form\CancelBookingType;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\Attribute\AsFormTool;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsFormTool(
    name: 'cancel_booking',
    form: CancelBookingType::class,
    description: 'Cancel an existing booking and release its seats.',
    title: 'Cancel a booking',
    submitLabel: 'Cancel booking',
)]
final class CancelBookingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LockedOperation $locked,
    ) {
    }

    /**
     * @throws ValidationFailedException when the booking was cancelled in the meantime
     */
    public function __invoke(CancelRequest $request): string
    {
        $booking = $request->booking;
        if (null === $booking) {
            throw new \LogicException('A cancel request must be validated before it is handled.');
        }

        return $this->locked->run($request, [$booking], function () use ($booking, $request): string {
            $booking->cancel($request->reason);
            $this->entityManager->flush();

            return \sprintf(
                'Booking #%d for %s is cancelled. %s in %s %s free again.',
                $booking->getId(),
                $booking->getName(),
                ucfirst($booking->getSeatsLabel()),
                $booking->getSession()->getTitle(),
                1 === $booking->getSeats() ? 'is' : 'are',
            );
        });
    }
}
