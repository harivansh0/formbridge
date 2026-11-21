<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    /**
     * symfony/ai-bundle 1.0.0-BETA1 registers its #[IsGrantedTool] listener whenever
     * symfony/security-core is installed, although the listener needs SecurityBundle's
     * authorization checker.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('security.authorization_checker')) {
            $container->removeDefinition('ai.security.is_granted_attribute_listener');
        }
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
