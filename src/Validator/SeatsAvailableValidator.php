<?php

declare(strict_types=1);

namespace App\Validator;

use App\Booking\BookingRequest;
use App\Booking\RescheduleRequest;
use App\Repository\SessionRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class SeatsAvailableValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SessionRepository $sessions,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SeatsAvailable) {
            throw new UnexpectedTypeException($constraint, SeatsAvailable::class);
        }

        [$session, $seats, $path] = match (true) {
            $value instanceof BookingRequest => [$value->session, $value->seats, 'session'],
            $value instanceof RescheduleRequest => [$value->newSession, $value->booking?->getSeats(), 'newSession'],
            default => throw new UnexpectedValueException($value, BookingRequest::class.'|'.RescheduleRequest::class),
        };
        if (null === $session || null === $seats || $seats < 1) {
            return;
        }
        // A move that RescheduleRequest refuses on its own needs no seat count.
        if ($value instanceof RescheduleRequest && ($value->booking?->isCancelled() || $value->booking?->getSession() === $session)) {
            return;
        }

        $left = $this->sessions->countSeatsLeft($session);
        if ($seats <= $left) {
            return;
        }

        $this->context
            ->buildViolation(0 === $left ? $constraint->fullMessage : $constraint->tooFewMessage)
            ->setParameter('{{ session }}', $session->getTitle())
            ->setParameter('{{ left }}', 1 === $left ? '1 seat' : $left.' seats')
            ->setParameter('{{ seats }}', (string) $seats)
            ->atPath($path)
            ->addViolation();
    }
}
