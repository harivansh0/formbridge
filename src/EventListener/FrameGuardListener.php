<?php

declare(strict_types=1);

namespace App\EventListener;

use FormBridge\EventListener\FrameGuardListener as FormBridgeFrameGuard;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * No page of the application may be framed by another site: every page has a button that saves.
 */
final class FrameGuardListener
{
    #[AsEventListener]
    public function onResponse(ResponseEvent $event): void
    {
        FormBridgeFrameGuard::guard($event->getResponse()->headers);
    }
}
