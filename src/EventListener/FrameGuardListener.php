<?php

declare(strict_types=1);

namespace App\EventListener;

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
        $headers = $event->getResponse()->headers;

        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
    }
}
