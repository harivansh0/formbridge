<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Booking;
use Doctrine\ORM\EntityManagerInterface;
use FormBridge\DraftStore;
use FormBridge\FormToolRunner;
use FormBridge\Mcp\FormToolResponder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A draft can be reached through the form in the conversation and through the review page, and
 * saved once, whichever way is used.
 */
final class SavingOnceTest extends WebTestCase
{
    use ResetsDatabase;

    private const JOHN = ['session' => 'sat-10', 'seats' => 2, 'name' => 'John Doe', 'email' => 'john@example.com'];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::resetDatabase();
    }

    public function testThePersonSavesOnTheReviewPage(): void
    {
        ['reviewUrl' => $reviewUrl] = $this->draft();

        $page = $this->client->request('GET', $reviewUrl);
        self::assertResponseIsSuccessful();
        self::assertSame('John Doe', $page->filter('input[name="name"]')->attr('value'));

        $this->client->submit($page->selectButton('Book')->form());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.fb-done', 'Booking #5: 2 seats in Saturday 10:00 Sourdough basics for John Doe');
        self::assertSame(5, $this->countBookings());
    }

    public function testTheLinkAloneIsNotEnoughToSave(): void
    {
        ['reviewUrl' => $reviewUrl] = $this->draft();

        $this->client->request('POST', $reviewUrl, self::JOHN);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(4, $this->countBookings());
    }

    public function testSavingOnTheReviewPageClosesTheFormInTheConversation(): void
    {
        ['reviewUrl' => $reviewUrl, 'submitToken' => $submitToken] = $this->draft();
        $this->client->submit($this->client->request('GET', $reviewUrl)->selectButton('Book')->form());
        self::assertSame(5, $this->countBookings());

        $drafts = self::getContainer()->get(DraftStore::class);

        self::assertNull($drafts->findBySubmitToken($submitToken, 'create_booking'));
        $this->client->request('GET', $reviewUrl);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSavingInTheConversationClosesTheReviewLink(): void
    {
        ['reviewUrl' => $reviewUrl, 'submitToken' => $submitToken] = $this->draft();
        $container = self::getContainer();
        $drafts = $container->get(DraftStore::class);
        $runner = $container->get(FormToolRunner::class);

        $draft = $drafts->findBySubmitToken($submitToken, 'create_booking');
        self::assertNotNull($draft);
        self::assertTrue($drafts->claim($draft, static fn () => $runner->submit('create_booking', self::JOHN))?->saved);

        self::assertNull($drafts->claim($draft, static fn () => $runner->submit('create_booking', self::JOHN)));
        $this->client->request('GET', $reviewUrl);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(5, $this->countBookings());
    }

    public function testASaveThatWasInterruptedCannotBeRepeated(): void
    {
        ['reviewUrl' => $reviewUrl, 'submitToken' => $submitToken] = $this->draft();
        $drafts = self::getContainer()->get(DraftStore::class);
        $draft = $drafts->findBySubmitToken($submitToken, 'create_booking');
        self::assertNotNull($draft);

        $runner = self::getContainer()->get(FormToolRunner::class);

        // The handler saves, then the request fails before the outcome is returned.
        try {
            $drafts->claim($draft, static function () use ($runner): never {
                $runner->submit('create_booking', self::JOHN);

                throw new \RuntimeException('The request failed after saving.');
            });
            self::fail('The interruption was swallowed.');
        } catch (\RuntimeException) {
        }

        self::assertSame(5, $this->countBookings());
        self::assertNull($drafts->findBySubmitToken($submitToken, 'create_booking'));
        $this->client->request('GET', $reviewUrl);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCorrectingAFormDoesNotExtendItsLifetime(): void
    {
        ['submitToken' => $submitToken] = $this->draft(['session' => 'fri-19'] + self::JOHN);
        $container = self::getContainer();
        $drafts = $container->get(DraftStore::class);
        $runner = $container->get(FormToolRunner::class);
        $draft = $drafts->findBySubmitToken($submitToken, 'create_booking');
        self::assertNotNull($draft);

        $outcome = $drafts->claim($draft, static fn () => $runner->submit('create_booking', ['session' => 'fri-19'] + self::JOHN));

        self::assertFalse($outcome?->saved);
        self::assertEquals($draft->expiresAt, $drafts->findBySubmitToken($submitToken, 'create_booking')?->expiresAt);
        self::assertLessThanOrEqual(3600, $draft->expiresAt->getTimestamp() - time());
    }

    public function testAFormThatIsNotValidStaysOpenForCorrection(): void
    {
        ['reviewUrl' => $reviewUrl] = $this->draft(['session' => 'fri-19'] + self::JOHN);

        $page = $this->client->request('GET', $reviewUrl);
        self::assertSelectorTextContains('form ul', 'Friday 19:00 Sourdough basics has only 1 seat left, and you asked for 2.');

        $page = $this->client->submit($page->selectButton('Book')->form());
        self::assertResponseStatusCodeSame(422);

        $this->client->submit($page->selectButton('Book')->form(['session' => 'sun-14']));
        self::assertSelectorTextContains('.fb-done', 'Sunday 14:00 Croissants');
    }

    public function testFormBridgePagesAreNotShownInsideAnotherWebsite(): void
    {
        ['reviewUrl' => $reviewUrl] = $this->draft();

        $this->client->request('GET', $reviewUrl);

        self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'self'");
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array{reviewUrl: string, submitToken: string}
     */
    private function draft(array $values = self::JOHN): array
    {
        $container = self::getContainer();
        $result = $container->get(FormToolResponder::class)->forDraft($container->get(FormToolRunner::class)->draft('create_booking', $values), $values);

        $form = new Crawler($result->meta['formbridge/html']);

        return [
            'reviewUrl' => (string) parse_url($result->structuredContent['reviewUrl'], \PHP_URL_PATH),
            'submitToken' => (string) $form->filter('input[name="_formbridge_token"]')->attr('value'),
        ];
    }

    private function countBookings(): int
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->getRepository(Booking::class)->count([]);
    }
}
