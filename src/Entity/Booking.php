<?php

declare(strict_types=1);

namespace App\Entity;

use App\Booking\BookingStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: 16, enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::Confirmed;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 300, nullable: true)]
    private ?string $cancellationReason = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Session::class, inversedBy: 'bookings')]
        #[ORM\JoinColumn(nullable: false)]
        private Session $session,
        #[ORM\Column]
        private int $seats,
        #[ORM\Column(length: 80)]
        private string $name,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 500, nullable: true)]
        private ?string $notes = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $session->getBookings()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function moveTo(Session $session): void
    {
        $this->session->getBookings()->removeElement($this);
        $this->session = $session;
        $session->getBookings()->add($this);
    }

    public function cancel(?string $reason = null): void
    {
        $this->status = BookingStatus::Cancelled;
        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancellationReason = $reason;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function isCancelled(): bool
    {
        return BookingStatus::Cancelled === $this->status;
    }

    public function getSeats(): int
    {
        return $this->seats;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getTotal(): int
    {
        return $this->seats * $this->session->getPricePerSeat();
    }

    public function getSeatsLabel(): string
    {
        return $this->seats.(1 === $this->seats ? ' seat' : ' seats');
    }

    public function __toString(): string
    {
        return \sprintf(
            'Booking #%d: %s in %s for %s, €%d in total.',
            $this->id,
            $this->getSeatsLabel(),
            $this->session->getTitle(),
            $this->name,
            $this->getTotal(),
        );
    }
}
