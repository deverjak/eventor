<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * `POST /api/auth/logout` ends the session and answers `204 No Content` (the default would redirect).
 */
#[AsEventListener(event: LogoutEvent::class)]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}
