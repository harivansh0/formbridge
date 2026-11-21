<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class SeatsAvailable extends Constraint
{
    public string $fullMessage = '{{ session }} is fully booked.';
    public string $tooFewMessage = '{{ session }} has only {{ left }} left, and you asked for {{ seats }}.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
