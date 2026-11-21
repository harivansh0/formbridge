<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Booking;
use App\Repository\BookingRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A choice among the bookings that are still confirmed.
 */
final class BookingChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Booking::class,
            'query_builder' => static fn (BookingRepository $bookings) => $bookings->createConfirmedQueryBuilder(),
            'choice_label' => static fn (Booking $booking): string => \sprintf(
                '#%d %s, %s in %s',
                $booking->getId(),
                $booking->getName(),
                $booking->getSeatsLabel(),
                $booking->getSession()->getTitle(),
            ),
            'placeholder' => 'Choose a booking',
        ]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }
}
