<?php

declare(strict_types=1);

namespace App\Tests;

use App\DataFixtures\WorkshopFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Rebuilds the test database with the workshop fixtures, so identifiers start at 1 in every test.
 */
trait ResetsDatabase
{
    private static function resetDatabase(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        (new WorkshopFixtures())->load($entityManager);
        $entityManager->clear();
    }
}
