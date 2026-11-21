<?php

declare(strict_types=1);

namespace App\Tests;

use App\Assistant\Suggestions;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The "paste a message" page. Without a provider key the offline responder reads the email.
 */
final class FillFromMessageTest extends WebTestCase
{
    use ResetsDatabase;

    public function testAPastedMessageBecomesAFilledInFormToReview(): void
    {
        $client = self::createClient();
        self::resetDatabase();

        $page = $client->request('GET', '/_formbridge/fill/create_booking');
        self::assertResponseIsSuccessful();

        $client->submit($page->selectButton('Fill in the form')->form(['message[text]' => Suggestions::EMAIL]), [], ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseRedirects();
        $review = $client->followRedirect();

        self::assertSelectorTextContains('h2', 'Book a workshop');
        self::assertSame('sun-14', $review->filter('select[name="session"] option[selected]')->attr('value'));
        self::assertSame('Mia Hoffmann', $review->filter('input[name="name"]')->attr('value'));
        self::assertSame('2', $review->filter('input[name="seats"]')->attr('value'));
    }

    public function testAnEmptyMessageIsRefused(): void
    {
        $client = self::createClient();

        $page = $client->request('GET', '/_formbridge/fill/create_booking');
        $client->submit($page->selectButton('Fill in the form')->form(['message[text]' => '']), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form ul', 'Paste the message first.');
    }

    public function testReadingMessagesIsRateLimitedPerClient(): void
    {
        $client = self::createClient();
        // The test limiter counts in memory, so the kernel must stay up between requests.
        $client->disableReboot();
        self::resetDatabase();

        for ($i = 0; $i < 3; ++$i) {
            $page = $client->request('GET', '/_formbridge/fill/create_booking');
            $client->submit($page->selectButton('Fill in the form')->form(['message[text]' => Suggestions::EMAIL]), [], ['HTTP_ORIGIN' => 'http://localhost']);
            self::assertResponseRedirects();
        }

        $page = $client->request('GET', '/_formbridge/fill/create_booking');
        $client->submit($page->selectButton('Fill in the form')->form(['message[text]' => Suggestions::EMAIL]), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }

    public function testAMessageThatCannotBeReadIsAnsweredOnThePage(): void
    {
        $client = self::createClient();
        self::resetDatabase();

        $page = $client->request('GET', '/_formbridge/fill/create_booking');
        $client->submit($page->selectButton('Fill in the form')->form(['message[text]' => 'Three seats on Saturday, please.']), [], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('form ul', 'The message could not be read right now.');
    }

    public function testThereIsNoSuchPageForAnUnknownForm(): void
    {
        $client = self::createClient();

        $client->request('GET', '/_formbridge/fill/delete_everything');

        self::assertResponseStatusCodeSame(404);
    }
}
