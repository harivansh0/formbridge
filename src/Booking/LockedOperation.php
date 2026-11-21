<?php

declare(strict_types=1);

namespace App\Booking;

use App\Entity\Booking;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runs a change to bookings under the locks of the booking being changed and of the session that
 * would receive seats.
 *
 * A request that was valid when the form was checked is validated again inside the lock, against
 * the data as it is then, so two requests cannot both take the last seat.
 */
final class LockedOperation
{
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @template T
     *
     * @param list<Session|Booking> $entities
     * @param callable(): T         $operation must commit its own writes before it returns
     *
     * @return T
     *
     * @throws ValidationFailedException when the request no longer holds
     */
    public function run(object $request, array $entities, callable $operation): mixed
    {
        // The locks are released when the operation returns; a caller's transaction would commit later.
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            throw new \LogicException('A locked operation cannot run inside another transaction.');
        }

        $keys = array_map(
            static fn (Session|Booking $entity): string => ($entity instanceof Session ? 'workshop-session-' : 'workshop-booking-').$entity->getId(),
            $entities,
        );
        // Always in the same order, so two operations can never wait on each other.
        sort($keys);

        $locks = [];
        try {
            foreach ($keys as $key) {
                $lock = $this->lockFactory->createLock($key);
                $lock->acquire(true);
                $locks[] = $lock;
            }

            foreach ($entities as $entity) {
                $this->entityManager->refresh($entity);
            }

            $violations = $this->validator->validate($request);
            if (\count($violations) > 0) {
                throw new ValidationFailedException($request, $violations);
            }

            return $operation();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }
}
