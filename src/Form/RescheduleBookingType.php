<?php

declare(strict_types=1);

namespace App\Form;

use App\Booking\RescheduleRequest;
use App\Entity\Session;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RescheduleBookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('booking', BookingChoiceType::class)
            ->add('newSession', EntityType::class, [
                'class' => Session::class,
                'label' => 'Move to',
                'choice_label' => static fn (Session $session): string => \sprintf('%s (%s)', $session->getTitle(), $session->getAvailability()),
                'placeholder' => 'Choose a session',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RescheduleRequest::class]);
    }
}
