<?php

declare(strict_types=1);

namespace App\Assistant;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AskRequest
{
    /**
     * @param list<array{role: string, text: string}> $history earlier turns, oldest first
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public string $message = '',

        #[Assert\Count(max: 12)]
        #[Assert\All(constraints: [
            new Assert\Collection(fields: [
                'role' => new Assert\Choice(choices: ['user', 'assistant']),
                'text' => [new Assert\Type(type: 'string'), new Assert\Length(max: 4000)],
            ]),
        ])]
        public array $history = [],
    ) {
    }
}
