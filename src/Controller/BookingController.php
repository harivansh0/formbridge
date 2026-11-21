<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AssistantPlatform;
use App\Assistant\Suggestions;
use App\Booking\BookingRequest;
use App\Booking\CancelBookingHandler;
use App\Booking\CancelRequest;
use App\Booking\CreateBookingHandler;
use App\Booking\RescheduleBookingHandler;
use App\Booking\RescheduleRequest;
use App\Entity\Booking;
use App\Form\BookingType;
use App\Form\CancelBookingType;
use App\Form\RescheduleBookingType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class BookingController extends AbstractController
{
    #[Route('/book', name: 'book', methods: ['GET', 'POST'])]
    public function book(Request $request, CreateBookingHandler $createBooking, AssistantPlatform $platform): Response
    {
        $form = $this->createForm(BookingType::class, new BookingRequest());

        return $this->handle($request, $form, $createBooking, 'booking/book.html.twig', [
            // Offline, the form filler only understands this message.
            'example_email' => $platform->isLive() ? null : Suggestions::EMAIL,
            'filler_available' => $platform->isAvailable(),
        ]);
    }

    #[Route('/bookings/{id:booking}/move', name: 'booking_move', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function move(Request $request, Booking $booking, RescheduleBookingHandler $reschedule): Response
    {
        $move = new RescheduleRequest();
        $move->booking = $booking;

        return $this->handle($request, $this->createForm(RescheduleBookingType::class, $move), $reschedule, 'booking/change.html.twig', [
            'title' => 'Move a booking',
            'submit_label' => 'Move booking',
        ]);
    }

    #[Route('/bookings/{id:booking}/cancel', name: 'booking_cancel', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cancel(Request $request, Booking $booking, CancelBookingHandler $cancel): Response
    {
        $cancellation = new CancelRequest();
        $cancellation->booking = $booking;

        return $this->handle($request, $this->createForm(CancelBookingType::class, $cancellation), $cancel, 'booking/change.html.twig', [
            'title' => 'Cancel a booking',
            'submit_label' => 'Cancel booking',
        ]);
    }

    /**
     * @param callable(mixed): (\Stringable|string) $handler
     * @param array<string, mixed>                  $context
     */
    private function handle(Request $request, FormInterface $form, callable $handler, string $template, array $context = []): Response
    {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->addFlash('success', (string) $handler($form->getData()));

                return $this->redirectToRoute('home');
            } catch (ValidationFailedException $refused) {
                foreach ($refused->getViolations() as $violation) {
                    $form->addError(new FormError((string) $violation->getMessage()));
                }
            }
        }

        return $this->render($template, ['form' => $form] + $context);
    }
}
