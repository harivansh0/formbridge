<?php

declare(strict_types=1);

namespace App\Assistant;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The platform behind the assistant: the configured provider, or the offline responder when no
 * key is set. Running without a key is refused in production.
 */
final class AssistantPlatform implements PlatformInterface
{
    private ?PlatformInterface $offline = null;

    public function __construct(
        #[Autowire(service: 'ai.platform.generic.assistant')]
        private readonly PlatformInterface $provider,
        private readonly OfflineResponder $offlineResponder,
        #[Autowire(env: 'AI_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    public function isLive(): bool
    {
        return '' !== $this->apiKey;
    }

    /**
     * False in production without a key: there are no canned replies there.
     */
    public function isAvailable(): bool
    {
        return $this->isLive() || 'prod' !== $this->environment;
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        return $this->platform()->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->platform()->getModelCatalog();
    }

    private function platform(): PlatformInterface
    {
        if ($this->isLive()) {
            return $this->provider;
        }
        if (!$this->isAvailable()) {
            throw new \LogicException('The assistant needs a model provider key: set AI_API_KEY.');
        }

        return $this->offline ??= MockPlatformFactory::createPlatform($this->offlineResponder->__invoke(...));
    }
}
