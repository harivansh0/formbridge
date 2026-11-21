<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The same forms and handlers on the application's own pages.
 */
final class BookingPagesTest extends WebTestCase
{
    use ResetsDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::resetDatabase();
    }

    public function testBookingOnTheWebsite(): void
    {
        $page = $this->client->request('GET', '/book');
        $this->client->submit($page->selectButton('Book')->form([
            'booking[session]' => 'sat-10',
            'booking[seats]' => '2',
            'booking[name]' => 'Priya Nair',
            'booking[email]' => 'priya@example.com',
        ]), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Booking #5: 2 seats in Saturday 10:00 Sourdough basics for Priya Nair, €50 in total.');
    }

    public function testMovingABookingStartsFromThatBooking(): void
    {
        $page = $this->client->request('GET', '/bookings/4/move');

        self::assertResponseIsSuccessful();
        self::assertSame('4', $page->filter('select[name="reschedule_booking[booking]"] option[selected]')->attr('value'));

        $this->client->submit($page->selectButton('Move booking')->form(['reschedule_booking[newSession]' => 'sat-10']), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/');
        self::assertSame('sat-10', $this->booking(4)->getSession()->getId());
    }

    public function testAMoveIntoAFullSessionIsAnsweredOnTheForm(): void
    {
        $page = $this->client->request('GET', '/bookings/3/move');
        $this->client->submit($page->selectButton('Move booking')->form(['reschedule_booking[newSession]' => 'fri-19']), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form.booking ul', 'Friday 19:00 Sourdough basics has only 1 seat left, and this booking needs 4.');
    }

    public function testCancellingABooking(): void
    {
        $page = $this->client->request('GET', '/bookings/2/cancel');
        $this->client->submit($page->selectButton('Cancel booking')->form(['cancel_booking[reason]' => 'Double booking']), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseRedirects('/');
        $page = $this->client->followRedirect();
        self::assertTrue($this->booking(2)->isCancelled());
        self::assertSame('Cancelled', $page->filter('tr.cancelled .badge')->text());
        self::assertCount(0, $page->filter('tr.cancelled a'));
    }

    public function testNoPageMayBeFramedByAnotherSite(): void
    {
        foreach (['/', '/book', '/bookings/1/move', '/bookings/1/cancel', '/assistant'] as $path) {
            $this->client->request('GET', $path);

            self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'self'", $path);
            self::assertResponseHeaderSame('X-Frame-Options', 'SAMEORIGIN', $path);
        }
    }

    public function testAnUnknownBookingIsNotFound(): void
    {
        $this->client->request('GET', '/bookings/99/cancel');

        self::assertResponseStatusCodeSame(404);
    }

    private function booking(int $id): Booking
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(Booking::class, $id) ?? throw new \LogicException('No such booking.');
    }
}
