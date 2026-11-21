<?php

declare(strict_types=1);

namespace App\Assistant;

use FormBridge\Agent\DraftedForm;

final readonly class AssistantReply
{
    public function __construct(
        public string $text,
        public ?DraftedForm $form = null,
    ) {
    }
}
