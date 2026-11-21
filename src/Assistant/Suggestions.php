<?php

declare(strict_types=1);

namespace App\Assistant;

/**
 * The prompts suggested on the assistant screen, each with the tool call it leads to.
 */
final class Suggestions
{
    public const EMAIL = "Hello,\n\nI would like two places in the croissant workshop on Sunday afternoon, for my partner and me. One of us cannot eat nuts.\n\nMy name is Mia Hoffmann and you can reach me at mia.hoffmann@example.com.\n\nThank you!";

    public const EMAIL_VALUES = [
        'session' => 'sun-14',
        'seats' => 2,
        'name' => 'Mia Hoffmann',
        'email' => 'mia.hoffmann@example.com',
        'notes' => 'One of us cannot eat nuts.',
    ];

    private const ALL = [
        [
            'prompt' => 'Book 2 seats on Friday for Priya Nair, priya@example.com',
            'tool' => 'create_booking',
            'arguments' => ['session' => 'fri-19', 'seats' => 2, 'name' => 'Priya Nair', 'email' => 'priya@example.com'],
        ],
        [
            'prompt' => 'Book 1 seat on Sunday for Sam Okoro, sam@example.com. He is vegetarian.',
            'tool' => 'create_booking',
            'arguments' => ['session' => 'sun-14', 'seats' => 1, 'name' => 'Sam Okoro', 'email' => 'sam@example.com', 'notes' => 'Vegetarian'],
        ],
        [
            'prompt' => "Move Lena Fischer's booking to Friday",
            'tool' => 'reschedule_booking',
            'arguments' => ['newSession' => 'fri-19'],
            'bookingOf' => 'Lena Fischer',
        ],
        [
            'prompt' => "Cancel Omar Haddad's booking, he is ill",
            'tool' => 'cancel_booking',
            'arguments' => ['reason' => 'He is ill.'],
            'bookingOf' => 'Omar Haddad',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function prompts(): array
    {
        return array_column(self::ALL, 'prompt');
    }

    /**
     * @return array{prompt: string, tool: string, arguments: array<string, mixed>, bookingOf?: string}|null
     */
    public static function find(string $text): ?array
    {
        foreach (self::ALL as $suggestion) {
            if (self::same($suggestion['prompt'], $text)) {
                return $suggestion;
            }
        }

        return null;
    }

    public static function isTheEmail(string $text): bool
    {
        return self::same(self::EMAIL, $text);
    }

    private static function same(string $one, string $other): bool
    {
        $normalize = static fn (string $value): string => strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));

        return $normalize($one) === $normalize($other);
    }
}
