<?php

declare(strict_types=1);

namespace App\Assistant;

use FormBridge\Agent\DraftLog;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class BookingAssistant
{
    public function __construct(
        #[Autowire(service: 'ai.agent.assistant')]
        private readonly AgentInterface $agent,
        private readonly DraftLog $drafts,
    ) {
    }

    public function reply(AskRequest $ask): AssistantReply
    {
        $turns = [];
        foreach ($ask->history as $turn) {
            $turns[] = 'user' === $turn['role'] ? Message::ofUser($turn['text']) : Message::ofAssistant($turn['text']);
        }
        $turns[] = Message::ofUser($ask->message);

        $this->drafts->reset();
        $text = $this->agent->call(new MessageBag(...$turns))->asText();

        // An agent may fill a form in several times while correcting itself; the last attempt counts.
        return new AssistantReply($text, $this->drafts->last());
    }
}
