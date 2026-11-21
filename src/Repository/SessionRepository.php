<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
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
     * Reloads a session and the state of its bookings from the database.
     *
     * refresh() alone reloads the collection but keeps the state of bookings that are already in
     * memory, so a booking cancelled by another request would still count as confirmed.
     */
    public function reload(Session $session): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->refresh($session);

        $entityManager->createQuery('SELECT booking FROM '.Booking::class.' booking WHERE booking.session = :session')
            ->setParameter('session', $session)
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();
    }
}
