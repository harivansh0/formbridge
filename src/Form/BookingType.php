<?php

declare(strict_types=1);

namespace App\Form;

use App\Booking\BookingRequest;
use App\Entity\Session;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('session', EntityType::class, [
                'class' => Session::class,
                'choice_label' => static fn (Session $session): string => \sprintf(
                    '%s (%s, €%d per seat)',
                    $session->getTitle(),
                    $session->getAvailability(),
                    $session->getPricePerSeat(),
                ),
                'placeholder' => 'Choose a session',
            ])
            ->add('seats', IntegerType::class, [
                'help' => 'Up to 4 seats per booking.',
            ])
            ->add('name', TextType::class, [
                'label' => 'Attendee name',
            ])
            ->add('email', EmailType::class, [
                'help' => 'The confirmation is sent here.',
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'help' => 'Dietary needs, accessibility, anything we should know.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BookingRequest::class]);
    }
}
