<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\Session;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

            return;
        }

        $left = $this->newSession->getSeatsLeft();
        if ($this->booking->getSeats() > $left) {
            $context->buildViolation('{{ session }} has {{ left }} left, and this booking needs {{ seats }}.')
                ->setParameter('{{ session }}', $this->newSession->getTitle())
                ->setParameter('{{ left }}', 0 === $left ? 'no seats' : (1 === $left ? 'only 1 seat' : 'only '.$left.' seats'))
                ->setParameter('{{ seats }}', (string) $this->booking->getSeats())
                ->atPath('newSession')
                ->addViolation();
        }
    }
}
