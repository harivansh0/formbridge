<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CancelRequest
{
    #[Assert\NotNull(message: 'Choose the booking to cancel.')]
    public ?Booking $booking = null;

    #[Assert\Length(max: 300)]
    public ?string $reason = null;

    #[Assert\Callback]
    public function validateStillActive(ExecutionContextInterface $context): void
    {
        if (null !== $this->booking && $this->booking->isCancelled()) {
            $context->buildViolation('Booking #{{ id }} is already cancelled.')
                ->setParameter('{{ id }}', (string) $this->booking->getId())
                ->atPath('booking')
                ->addViolation();
        }
    }
}
