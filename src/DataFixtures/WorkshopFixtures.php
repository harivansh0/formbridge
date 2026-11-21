<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Booking;
use App\Entity\Session;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class WorkshopFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $friday = new Session('fri-19', 'Friday 19:00 Sourdough basics', 6, 25);
        $saturday = new Session('sat-10', 'Saturday 10:00 Sourdough basics', 8, 25);
        $sunday = new Session('sun-14', 'Sunday 14:00 Croissants', 8, 32);

        $bookings = [
            new Booking($friday, 3, 'Tom Reid', 'tom@example.com'),
            new Booking($friday, 2, 'Ana Costa', 'ana@example.com', 'Vegetarian'),
            new Booking($saturday, 4, 'Lena Fischer', 'lena@example.com'),
            new Booking($sunday, 1, 'Omar Haddad', 'omar@example.com'),
        ];

        foreach ([$friday, $saturday, $sunday, ...$bookings] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
    }
}
