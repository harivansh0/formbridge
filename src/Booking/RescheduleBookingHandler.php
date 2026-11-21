<?php

declare(strict_types=1);

namespace App\Booking;

use App\Form\RescheduleBookingType;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\Attribute\AsFormTool;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsFormTool(
    name: 'reschedule_booking',
    form: RescheduleBookingType::class,
    description: 'Move an existing booking to another workshop session.',
    title: 'Move a booking',
    submitLabel: 'Move booking',
)]
final class RescheduleBookingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LockedOperation $locked,
    ) {
    }

    /**
     * @throws ValidationFailedException when the seats went to someone else in the meantime
     */
    public function __invoke(RescheduleRequest $request): string
    {
        $booking = $request->booking;
        $newSession = $request->newSession;
        if (null === $booking || null === $newSession) {
            throw new \LogicException('A reschedule request must be validated before it is handled.');
        }

        return $this->locked->run($request, [$booking, $newSession], function () use ($booking, $newSession): string {
            $booking->moveTo($newSession);
            $this->entityManager->flush();

            return \sprintf('Booking #%d for %s now is in %s.', $booking->getId(), $booking->getName(), $newSession->getTitle());
        });
    }
}
