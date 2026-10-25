<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Every `/api` request is an XHR-style call, never a browser navigation. Marking it as such stops the firewall from
 * remembering a "target path" in the session when it answers 401 (which would otherwise create a session row for
 * every anonymous probe).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final class ApiRequestListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMainRequest() && str_starts_with($request->getPathInfo(), '/api/')) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }
    }
}
