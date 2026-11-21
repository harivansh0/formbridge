<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\Table(name: 'workshop_session')]
class Session
{
    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'session')]
    private Collection $bookings;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 32)]
        private string $id,
        #[ORM\Column(length: 120)]
        private string $title,
        #[ORM\Column]
        private int $capacity,
        #[ORM\Column]
        private int $pricePerSeat,
    ) {
        $this->bookings = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    /** In euros. */
    public function getPricePerSeat(): int
    {
        return $this->pricePerSeat;
    }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function getSeatsTaken(): int
    {
        $seats = 0;
        foreach ($this->bookings as $booking) {
            if (!$booking->isCancelled()) {
                $seats += $booking->getSeats();
            }
        }

        return $seats;
    }

    public function getSeatsLeft(): int
    {
        return max(0, $this->capacity - $this->getSeatsTaken());
    }

    public function getAvailability(): string
    {
        return match ($left = $this->getSeatsLeft()) {
            0 => 'full',
            1 => '1 seat left',
            default => $left.' seats left',
        };
    }
}
