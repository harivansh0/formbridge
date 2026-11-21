<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Session;
use App\Validator\SeatsAvailable;
use Symfony\Component\Validator\Constraints as Assert;

#[SeatsAvailable]
final class BookingRequest
{
    #[Assert\NotNull(message: 'Choose a session.')]
    public ?Session $session = null;

    #[Assert\NotNull(message: 'Say how many seats you need.')]
    #[Assert\Range(min: 1, max: 4, notInRangeMessage: 'You can book between {{ min }} and {{ max }} seats at once.')]
    public ?int $seats = null;

    #[Assert\NotBlank(message: 'Enter the name of the person attending.')]
    #[Assert\Length(max: 80)]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Enter an email address for the confirmation.')]
    #[Assert\Email(message: 'This is not a valid email address.')]
    public ?string $email = null;

    #[Assert\Length(max: 500)]
    public ?string $notes = null;
}
