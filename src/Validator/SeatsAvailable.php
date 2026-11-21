<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * The session a booking goes to must still have room for it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class SeatsAvailable extends Constraint
{
    public string $fullMessage = '{{ session }} is fully booked.';
    public string $tooFewMessage = '{{ session }} has only {{ left }} left, and you asked for {{ seats }}.';

    /**
     * @param list<string>|null $groups
     */
    public function __construct(?string $fullMessage = null, ?string $tooFewMessage = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->fullMessage = $fullMessage ?? $this->fullMessage;
        $this->tooFewMessage = $tooFewMessage ?? $this->tooFewMessage;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
