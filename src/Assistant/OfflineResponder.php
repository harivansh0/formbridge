<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Repository\BookingRepository;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * Answers in place of a model when no provider key is configured, for local development and CI.
 *
 * It only understands the suggested prompts. Everything after its answer is unchanged: the agent,
 * the tool call, the form.
 */
final class OfflineResponder
{
    public function __construct(
        private readonly BookingRepository $bookings,
    ) {
    }

    /**
     * @param array{messages?: list<array{role: string, content: mixed}>}|string $payload
     * @param array<string, mixed>                                               $options
     */
    public function __invoke(Model $model, array|string $payload, array $options): ResultInterface|string
    {
        $messages = \is_array($payload) ? $payload['messages'] ?? [] : [];
        $last = [] === $messages ? null : $messages[array_key_last($messages)];
        $text = \is_string($last['content'] ?? null) ? $last['content'] : '';

        if (isset($options['response_format'])) {
            if (!Suggestions::isTheEmail($text)) {
                throw new \RuntimeException('Offline mode only understands the example message. Set AI_API_KEY to read any message.');
            }

            return json_encode(Suggestions::EMAIL_VALUES, \JSON_THROW_ON_ERROR);
        }

        if ('tool' === ($last['role'] ?? null)) {
            if (1 === preg_match('/^- [^:]+: (.+?)(?: Options now: .*)?$/m', $text, $problem)) {
                return 'I filled in the form, but it says: "'.$problem[1].'" Please change it in the form below, then press the button.';
            }

            return 'I filled in the form for you. Please check it, then press the button. Nothing is saved until you do.';
        }

        $suggestion = Suggestions::find($text);
        if (null === $suggestion) {
            return 'I am running offline and only understand the suggestions below. Set AI_API_KEY to ask me anything.';
        }

        $arguments = $suggestion['arguments'];
        if (isset($suggestion['bookingOf'])) {
            $booking = $this->bookings->findOneConfirmedByName($suggestion['bookingOf']);
            if (null !== $booking) {
                $arguments = ['booking' => (string) $booking->getId()] + $arguments;
            }
        }

        return new ToolCallResult([new ToolCall(bin2hex(random_bytes(6)), $suggestion['tool'], $arguments)]);
    }
}
