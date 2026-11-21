<?php

declare(strict_types=1);

namespace App\Repository;

use App\Booking\BookingStatus;
use App\Entity\Booking;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Seats still free, counted in the database.
     *
     * Bookings made or cancelled by other requests count at once. Changes that are not flushed
     * yet do not, and entities in memory are left as they are.
     */
    public function countSeatsLeft(Session $session): int
    {
        $taken = (int) $this->getEntityManager()
            ->createQuery('SELECT COALESCE(SUM(booking.seats), 0) FROM '.Booking::class.' booking WHERE booking.session = :session AND booking.status = :status')
            ->setParameter('session', $session)
            ->setParameter('status', BookingStatus::Confirmed)
            ->getSingleScalarResult();

        return max(0, $session->getCapacity() - $taken);
    }
}
