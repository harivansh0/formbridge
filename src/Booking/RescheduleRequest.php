<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\Session;
use App\Validator\SeatsAvailable;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[SeatsAvailable(
    fullMessage: '{{ session }} has no seats left, and this booking needs {{ seats }}.',
    tooFewMessage: '{{ session }} has only {{ left }} left, and this booking needs {{ seats }}.',
)]
final class RescheduleRequest
{
    #[Assert\NotNull(message: 'Choose the booking to move.')]
    public ?Booking $booking = null;

    #[Assert\NotNull(message: 'Choose the session to move it to.')]
    public ?Session $newSession = null;

    #[Assert\Callback]
    public function validateMove(ExecutionContextInterface $context): void
    {
        if (null === $this->booking || null === $this->newSession) {
            return;
        }

        if ($this->booking->isCancelled()) {
            $context->buildViolation('Booking #{{ id }} was cancelled and cannot be moved.')
                ->setParameter('{{ id }}', (string) $this->booking->getId())
                ->atPath('booking')
                ->addViolation();

            return;
        }

        if ($this->booking->getSession() === $this->newSession) {
            $context->buildViolation('This booking is already in {{ session }}.')
                ->setParameter('{{ session }}', $this->newSession->getTitle())
                ->atPath('newSession')
                ->addViolation();
        }
    }
}
