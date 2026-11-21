<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WorkshopController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(SessionRepository $sessions, BookingRepository $bookings): Response
    {
        return $this->render('workshop/home.html.twig', [
            'sessions' => $sessions->findAll(),
            'bookings' => $bookings->findLatest(),
        ]);
    }
}
