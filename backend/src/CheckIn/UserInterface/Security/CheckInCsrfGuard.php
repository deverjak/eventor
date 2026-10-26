<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Security;

use Eventor\Shared\UserInterface\Http\CsrfGuardListener;
use Eventor\Shared\UserInterface\Http\CsrfTokenInvalid;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The check-in API has two ways in (ADR 006): a device token, which is not cookie-based and needs no CSRF protection,
 * and the cookie session of a team member, which does. The shared CsrfGuardListener lists only `/api/admin` and
 * `/api/auth`, so this guard applies the same rule to the unsafe requests under `/api/check-in` that do not carry an
 * `Authorization` header: a same-site `Origin` / `Referer` and a random `csrf-token` header.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class CheckInCsrfGuard
{
    private const string PREFIX = '/api/check-in';

    public function __construct(private CsrfTokenManagerInterface $tokens) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->isMethodSafe() || $request->headers->has('Authorization')) {
            return;
        }

        $path = $request->getPathInfo();
        if (self::PREFIX !== $path && !str_starts_with($path, self::PREFIX.'/')) {
            return;
        }

        $token = $request->headers->get(CsrfGuardListener::HEADER);
        if (!\is_string($token) || !$this->tokens->isTokenValid(new CsrfToken(CsrfGuardListener::TOKEN_ID, $token))) {
            throw CsrfTokenInvalid::create();
        }
    }
}
