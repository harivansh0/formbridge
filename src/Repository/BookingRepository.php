<?php

declare(strict_types=1);

namespace App\Repository;

use App\Booking\BookingStatus;
use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * @return list<Booking>
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->findBy([], ['id' => 'DESC'], $limit);
    }

    public function createConfirmedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('booking')
            ->andWhere('booking.status = :status')
            ->setParameter('status', BookingStatus::Confirmed)
            ->orderBy('booking.id', 'ASC');
    }

    public function findOneConfirmedByName(string $name): ?Booking
    {
        return $this->createConfirmedQueryBuilder()
            ->andWhere('booking.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
