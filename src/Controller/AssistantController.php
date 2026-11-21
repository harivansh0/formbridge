<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AskRequest;
use App\Assistant\AssistantPlatform;
use App\Assistant\BookingAssistant;
use App\Assistant\Suggestions;
use FormBridge\Mcp\FormToolResponder;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AssistantController extends AbstractController
{
    #[Route('/assistant', name: 'assistant', methods: ['GET'])]
    public function screen(AssistantPlatform $platform, #[Autowire(env: 'AI_MODEL')] string $model): Response
    {
        return $this->render('assistant/screen.html.twig', [
            'suggestions' => Suggestions::prompts(),
            'live' => $platform->isLive(),
            'model' => $model,
        ]);
    }

    #[Route('/assistant/ask', name: 'assistant_ask', methods: ['POST'])]
    public function ask(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json')] AskRequest $ask,
        BookingAssistant $assistant,
        FormToolResponder $responder,
        RateLimiterFactoryInterface $assistantLimiter,
        LoggerInterface $logger,
    ): JsonResponse {
        // Questions cost money at the model provider, so they must come from the assistant screen.
        if (!$this->isCsrfTokenValid('assistant', $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['error' => 'Open the assistant screen and ask from there.'], Response::HTTP_FORBIDDEN);
        }

        $limit = $assistantLimiter->create($request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(
                ['error' => 'Too many questions in a short time. Try again in a moment.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
            );
        }

        try {
            $reply = $assistant->reply($ask);
        } catch (\Throwable $error) {
            $logger->error('The assistant could not answer: {message}', ['message' => $error->getMessage(), 'exception' => $error]);

            return new JsonResponse(
                ['error' => 'The assistant is not available right now.'.($this->getParameter('kernel.debug') ? ' '.$error->getMessage() : '')],
                Response::HTTP_BAD_GATEWAY,
            );
        }

        return new JsonResponse([
            'reply' => $reply->text,
            'form' => null === $reply->form ? null : [
                'tool' => $reply->form->tool,
                'arguments' => $reply->form->arguments,
                'result' => $responder->forDraft($reply->form->outcome, $reply->form->arguments),
            ],
        ]);
    }
}
