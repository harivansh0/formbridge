<?php

declare(strict_types=1);

namespace App\Tests\FormBridge;

use App\Entity\Booking;
use App\Tests\ResetsDatabase;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\Ai\FormFiller;
use FormBridge\FormToolRegistry;
use FormBridge\FormToolRunner;
use FormBridge\FormToolSchemas;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Free text is read into a form by a model, and the form has the last word.
 */
final class FormFillerTest extends KernelTestCase
{
    use ResetsDatabase;

    /** @var list<array<string, mixed>> the options of every call made to the model */
    private array $modelCalls = [];

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
    }

    public function testTheModelIsAskedForOutputInTheShapeOfTheForm(): void
    {
        $this->fillerAnswering('{}')->fill('create_booking', 'Two seats on Sunday please.');

        $format = $this->modelCalls[0]['response_format'];
        self::assertSame('json_schema', $format['type']);
        self::assertSame(['session', 'seats', 'name', 'email', 'notes'], array_keys($format['json_schema']['schema']['properties']));
        self::assertStringContainsString('sun-14 = "Sunday 14:00 Croissants', $format['json_schema']['schema']['properties']['session']['description']);
    }

    public function testWhatTheModelReadsIsPutThroughTheForm(): void
    {
        $outcome = $this->fillerAnswering('{"session": "sun-14", "seats": 2, "name": "Mia Hoffmann", "email": "mia.hoffmann@example.com"}')
            ->fill('create_booking', 'Two places on Sunday for Mia Hoffmann, mia.hoffmann@example.com');

        self::assertTrue($outcome->valid);
        self::assertFalse($outcome->saved);
        self::assertSame('sun-14', $outcome->values['session']);
        self::assertSame(2, $outcome->values['seats']);
        self::assertSame(4, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Booking::class)->count([]));
    }

    public function testTheFormSaysWhatTheMessageLeftOut(): void
    {
        $outcome = $this->fillerAnswering('{"session": "sun-14", "seats": 2}')->fill('create_booking', 'Two places on Sunday.');

        self::assertFalse($outcome->valid);
        self::assertEqualsCanonicalizing(['name', 'email'], array_column($outcome->errors, 'field'));
    }

    public function testAValueTheFormDoesNotOfferIsAnsweredByTheForm(): void
    {
        $outcome = $this->fillerAnswering('{"session": "Sunday afternoon", "seats": 2}')->fill('create_booking', 'Two places on Sunday afternoon.');

        $errors = array_column($outcome->errors, null, 'field');
        self::assertSame('The selected choice is invalid.', $errors['session']['message']);
        self::assertSame(['fri-19', 'sat-10', 'sun-14'], array_column($errors['session']['choices'], 'value'));
    }

    public function testAnAnswerWrappedInACodeFenceOrCarryingUnknownFieldsIsStillRead(): void
    {
        $outcome = $this->fillerAnswering("```json\n{\"seats\": 3, \"discount\": \"50%\"}\n```")->fill('create_booking', 'Three seats.');

        self::assertSame(3, $outcome->values['seats']);
        self::assertNotContains('This form should not contain extra fields.', array_column($outcome->errors, 'message'));
    }

    public function testOnlyTheFormsOwnFieldsAreKeptInsideNestedGroupsToo(): void
    {
        $filler = $this->fillerAnswering('{}');
        $ownFields = new \ReflectionMethod($filler, 'ownFields');

        $kept = $ownFields->invoke($filler, ['title' => 'Offsite', 'discount' => '50%', 'address' => ['city' => 'Lyon', 'vip' => true, 'street' => '']], [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'address' => ['type' => 'object', 'properties' => ['street' => ['type' => 'string'], 'city' => ['type' => 'string']]],
            ],
        ]);

        self::assertSame(['title' => 'Offsite', 'address' => ['city' => 'Lyon']], $kept);
    }

    private function fillerAnswering(string $answer): FormFiller
    {
        $container = self::getContainer();
        $platform = MockPlatformFactory::createPlatform(function ($model, $payload, array $options) use ($answer): string {
            $this->modelCalls[] = $options;

            return $answer;
        });

        return new FormFiller(
            $platform,
            'test-model',
            $container->get(FormToolRegistry::class),
            $container->get(FormToolRunner::class),
            $container->get(FormToolSchemas::class),
        );
    }
}
