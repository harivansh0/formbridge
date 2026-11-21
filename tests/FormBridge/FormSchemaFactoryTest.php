<?php

declare(strict_types=1);

namespace App\Tests\FormBridge;

use App\Tests\ResetsDatabase;
use FormBridge\Form\FormSchemaFactory;
use FormBridge\FormToolRegistry;
use FormBridge\FormToolRunner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FormSchemaFactoryTest extends KernelTestCase
{
    use ResetsDatabase;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
    }

    public function testTheBookingFormBecomesAToolSchema(): void
    {
        $schema = $this->schemaOf('create_booking');

        self::assertSame(['session', 'seats', 'name', 'email', 'notes'], array_keys($schema['properties']));
        self::assertSame('integer', $schema['properties']['seats']['type']);
        self::assertSame('Attendee name', $schema['properties']['name']['title']);
        self::assertFalse($schema['additionalProperties']);
    }

    public function testAChoiceFromTheDatabaseIsDescribedWithItsSubmittedValuesAndLabels(): void
    {
        $session = $this->schemaOf('create_booking')['properties']['session'];

        self::assertStringContainsString('fri-19 = Friday 19:00 Sourdough basics (1 seat left', $session['description']);
        self::assertStringContainsString('sat-10 = Saturday 10:00 Sourdough basics (4 seats left', $session['description']);
        self::assertArrayNotHasKey('enum', $session);
    }

    public function testValidationRulesAreDescribedButNotEnforcedByTheSchema(): void
    {
        $schema = $this->schemaOf('create_booking');
        $seats = $schema['properties']['seats'];

        self::assertStringContainsString('Between 1 and 4.', $seats['description']);
        self::assertStringContainsString('Required.', $seats['description']);
        self::assertArrayNotHasKey('minimum', $seats);
        self::assertArrayNotHasKey('required', $schema);
        self::assertArrayNotHasKey('format', $schema['properties']['email']);
    }

    public function testASecondFormNeedsNoExtraCode(): void
    {
        $schema = $this->schemaOf('reschedule_booking');

        self::assertSame(['booking', 'newSession'], array_keys($schema['properties']));
        self::assertStringContainsString('3 = #3 Lena Fischer, 4 seats in Saturday 10:00 Sourdough basics', $schema['properties']['booking']['description']);
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaOf(string $toolName): array
    {
        $container = self::getContainer();
        $form = $container->get(FormToolRunner::class)->createForm($container->get(FormToolRegistry::class)->get($toolName));

        return $container->get(FormSchemaFactory::class)->inputSchema($form);
    }
}
