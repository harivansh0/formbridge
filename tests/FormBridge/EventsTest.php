<?php

declare(strict_types=1);

namespace App\Tests\FormBridge;

use App\Booking\BookingRequest;
use App\Entity\Booking;
use App\Repository\BookingRepository;
use App\Tests\ResetsDatabase;
use FormBridge\DraftStore;
use FormBridge\Event\FormDraftedEvent;
use FormBridge\Event\FormSavedEvent;
use FormBridge\Event\FormSubmitEvent;
use FormBridge\FormToolRunner;
use FormBridge\Mcp\FormToolResponder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class EventsTest extends KernelTestCase
{
    use ResetsDatabase;

    private const JOHN = ['session' => 'sat-10', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com'];

    /** @var list<object> */
    private array $events = [];
    private FormToolRunner $runner;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
        $this->runner = self::getContainer()->get(FormToolRunner::class);

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        foreach ([FormDraftedEvent::class, FormSavedEvent::class] as $event) {
            $dispatcher->addListener($event, function (object $event): void {
                $this->events[] = $event;
            });
        }
    }

    public function testADraftIsAnnouncedAndASaveCarriesTheHandlersResult(): void
    {
        $this->runner->draft('create_booking', self::JOHN);
        $this->runner->submit('create_booking', self::JOHN);

        self::assertCount(2, $this->events);
        [$drafted, $saved] = $this->events;

        self::assertInstanceOf(FormDraftedEvent::class, $drafted);
        self::assertFalse($drafted->outcome->saved);

        self::assertInstanceOf(FormSavedEvent::class, $saved);
        self::assertInstanceOf(BookingRequest::class, $saved->data);
        self::assertInstanceOf(Booking::class, $saved->result);
        self::assertSame(5, $saved->result->getId());
    }

    public function testAListenerCanRefuseAValidFormBeforeTheHandlerRuns(): void
    {
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(FormSubmitEvent::class, static function (FormSubmitEvent $event): void {
            if ('create_booking' === $event->tool->name) {
                $event->refuse('You may not book for other people.');
            }
        });

        $outcome = $this->runner->submit('create_booking', self::JOHN);

        self::assertFalse($outcome->saved);
        self::assertSame([['field' => '', 'message' => 'You may not book for other people.']], $outcome->errors);
        self::assertSame([], $this->events);
        self::assertSame(4, self::getContainer()->get(BookingRepository::class)->count([]));
    }

    public function testWithoutTheReviewPageNoLinkIsHandedOut(): void
    {
        $container = self::getContainer();
        $responder = new FormToolResponder($container->get('twig'), $container->get(DraftStore::class), $container->get('router'), reviewPage: false);

        $result = $responder->forDraft($this->runner->draft('create_booking', self::JOHN), self::JOHN);

        self::assertArrayNotHasKey('reviewUrl', $result->structuredContent);
        self::assertStringNotContainsString('/_formbridge/review/', $result->content[0]->text);
    }

    public function testNothingIsAnnouncedForAReviewOrARefusedSubmit(): void
    {
        $this->runner->review('create_booking', self::JOHN);
        $this->runner->submit('create_booking', ['session' => 'fri-19'] + self::JOHN);

        self::assertSame([], $this->events);
    }
}
