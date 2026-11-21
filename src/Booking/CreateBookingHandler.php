<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Form\BookingType;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\Attribute\AsFormTool;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsFormTool(
    name: 'create_booking',
    form: BookingType::class,
    description: 'Book seats for one person in a workshop session.',
    title: 'Book a workshop',
    submitLabel: 'Book',
)]
final class CreateBookingHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LockedOperation $locked,
    ) {
    }

    /**
     * @throws ValidationFailedException when the seats went to someone else in the meantime
     */
    public function __invoke(BookingRequest $request): Booking
    {
        $session = $request->session;
        $seats = $request->seats;
        $name = $request->name;
        $email = $request->email;
        if (null === $session || null === $seats || null === $name || null === $email) {
            throw new \LogicException('A booking request must be validated before it is handled.');
        }

        return $this->locked->run($request, [$session], function () use ($session, $seats, $name, $email, $request): Booking {
            $booking = new Booking($session, $seats, $name, $email, $request->notes);
            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            return $booking;
        });
    }
}
