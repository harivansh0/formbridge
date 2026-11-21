<?php

declare(strict_types=1);

namespace App\Tests;

use App\Booking\BookingRequest;
use App\Booking\CreateBookingHandler;
use App\Booking\RescheduleBookingHandler;
use App\Booking\RescheduleRequest;
use App\Entity\Booking;
use App\Entity\Session;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A request that was valid when it was checked is refused if the seats are gone by the time it
 * is handled.
 */
final class LastSeatTest extends KernelTestCase
{
    use ResetsDatabase;

    public function testARequestThatWasValidIsRefusedOnceTheSeatsAreGone(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        self::resetDatabase();
        $entityManager = $container->get(EntityManagerInterface::class);
        $handler = $container->get(CreateBookingHandler::class);

        $friday = $entityManager->find(Session::class, 'fri-19');
        $first = $this->requestFor($friday, 'Ana Costa');
        $second = $this->requestFor($friday, 'Tom Reid');

        $validator = $container->get(ValidatorInterface::class);
        self::assertCount(0, $validator->validate($first));
        self::assertCount(0, $validator->validate($second));

        $handler($first);

        try {
            $handler($second);
            self::fail('The second booking of the last seat was accepted.');
        } catch (ValidationFailedException $refused) {
            self::assertSame('Friday 19:00 Sourdough basics is fully booked.', (string) $refused->getViolations()[0]->getMessage());
        }

        $entityManager->clear();
        self::assertSame(0, $entityManager->find(Session::class, 'fri-19')->getSeatsLeft());
        self::assertSame(5, $entityManager->getRepository(Booking::class)->count([]));
    }

    public function testAMoveIsRefusedOnceTheSeatItWasCheckedForIsGone(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        self::resetDatabase();
        $entityManager = $container->get(EntityManagerInterface::class);

        $move = new RescheduleRequest();
        $move->booking = $entityManager->getRepository(Booking::class)->findOneBy(['name' => 'Omar Haddad']);
        $move->newSession = $entityManager->find(Session::class, 'fri-19');
        self::assertCount(0, $container->get(ValidatorInterface::class)->validate($move));

        // Someone books Friday's last seat before the move is carried out.
        $container->get(CreateBookingHandler::class)($this->requestFor($move->newSession, 'Ana Costa'));

        try {
            $container->get(RescheduleBookingHandler::class)($move);
            self::fail('The move into a full session was accepted.');
        } catch (ValidationFailedException $refused) {
            self::assertSame('Friday 19:00 Sourdough basics has no seats left, and this booking needs 1.', (string) $refused->getViolations()[0]->getMessage());
        }

        $entityManager->clear();
        self::assertSame(0, $entityManager->find(Session::class, 'fri-19')->getSeatsLeft());
        self::assertSame('Sunday 14:00 Croissants', $entityManager->getRepository(Booking::class)->findOneBy(['name' => 'Omar Haddad'])->getSession()->getTitle());
    }

    public function testSeatsReleasedByAnotherRequestCountAtOnce(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // This request has Friday's bookings in memory: one seat left.
        $friday = $entityManager->find(Session::class, 'fri-19');
        self::assertSame(1, $friday->getSeatsLeft());

        // Another request cancels Tom's three seats.
        $entityManager->getConnection()->executeStatement("UPDATE booking SET status = 'cancelled' WHERE name = 'Tom Reid'");

        $request = $this->requestFor($friday, 'Priya Nair');
        $request->seats = 4;
        self::getContainer()->get(CreateBookingHandler::class)($request);

        $entityManager->clear();
        self::assertSame(0, $entityManager->find(Session::class, 'fri-19')->getSeatsLeft());
    }

    public function testCheckingSeatsLeavesUnsavedChangesAlone(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $friday = $entityManager->find(Session::class, 'fri-19');

        // Tom's booking is cancelled in memory and not flushed yet.
        $tom = $entityManager->getRepository(Booking::class)->findOneBy(['name' => 'Tom Reid']);
        $tom->cancel('Changed plans');

        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($this->requestFor($friday, 'Priya Nair'));
        self::assertCount(0, $violations);
        self::assertTrue($tom->isCancelled(), 'Validating another request reverted an unsaved change.');

        $entityManager->flush();
        $entityManager->clear();
        self::assertTrue($entityManager->getRepository(Booking::class)->findOneBy(['name' => 'Tom Reid'])->isCancelled());
    }

    public function testAHandlerRefusesToRunInsideSomeoneElsesTransaction(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $request = $this->requestFor($entityManager->find(Session::class, 'sat-10'), 'Priya Nair');

        $entityManager->getConnection()->beginTransaction();
        try {
            $this->expectException(\LogicException::class);
            self::getContainer()->get(CreateBookingHandler::class)($request);
        } finally {
            $entityManager->getConnection()->rollBack();
        }
    }

    private function requestFor(Session $session, string $name): BookingRequest
    {
        $request = new BookingRequest();
        $request->session = $session;
        $request->seats = 1;
        $request->name = $name;
        $request->email = 'someone@example.com';

        return $request;
    }
}
