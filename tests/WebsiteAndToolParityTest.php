<?php

declare(strict_types=1);

namespace App\Tests;

use FormBridge\FormToolRunner;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebsiteAndToolParityTest extends WebTestCase
{
    use ResetsDatabase;

    public function testTheWebsiteAndTheToolGiveTheSameValidationMessages(): void
    {
        $client = self::createClient();
        self::resetDatabase();

        $values = ['session' => 'fri-19', 'seats' => '9', 'name' => '', 'email' => 'not-an-email', 'notes' => ''];

        $page = $client->request('GET', '/book');
        $form = $page->selectButton('Book')->form();
        foreach ($values as $field => $value) {
            $form['booking['.$field.']'] = $value;
        }
        $page = $client->submit($form, [], ['HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(422);
        $websiteMessages = $page->filter('form.booking ul li')->each(static fn ($item): string => $item->text());

        $toolMessages = array_column(
            self::getContainer()->get(FormToolRunner::class)->draft('create_booking', $values)->errors,
            'message',
        );

        self::assertCount(4, $toolMessages);
        self::assertEqualsCanonicalizing($websiteMessages, $toolMessages);
    }
}
