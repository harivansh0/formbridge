<?php

declare(strict_types=1);

namespace App\Validator;

use App\Booking\BookingRequest;
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
        if (!$value instanceof BookingRequest) {
            throw new UnexpectedValueException($value, BookingRequest::class);
        }
        if (null === $value->session || null === $value->seats || $value->seats < 1) {
            return;
        }

        // The session may have been loaded before the latest bookings were made.
        $this->sessions->reload($value->session);
        $left = $value->session->getSeatsLeft();
        if ($value->seats <= $left) {
            return;
        }

        $this->context
            ->buildViolation(0 === $left ? $constraint->fullMessage : $constraint->tooFewMessage)
            ->setParameter('{{ session }}', $value->session->getTitle())
            ->setParameter('{{ left }}', 1 === $left ? '1 seat' : $left.' seats')
            ->setParameter('{{ seats }}', (string) $value->seats)
            ->atPath('session')
            ->addViolation();
    }
}
