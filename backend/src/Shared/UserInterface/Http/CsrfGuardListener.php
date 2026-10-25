<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF protection of the cookie-authenticated API (ADR 006). Unsafe requests (POST, PUT, PATCH, DELETE) to the
 * team-session zone must be provably same-site:
 *
 *  - the stateless SameOriginCsrfTokenManager rejects a request whose `Origin`/`Referer` (or `Sec-Fetch-Site`)
 *    names another site, and
 *  - the client must send a random token (>= 24 characters, e.g. `crypto.randomUUID()`) in the `csrf-token`
 *    header. A cross-site page cannot set a custom header without a CORS preflight, and CORS is not enabled.
 *
 * There is nothing to fetch: any client generates its own token (the test clients send a fresh one per request).
 * Zones with their own credentials (public, self-service, webhooks, check-in devices) are not cookie-based and
 * are therefore not listed.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class CsrfGuardListener
{
    public const string HEADER = 'csrf-token';
    public const string TOKEN_ID = 'api';

    /**
     * @param list<string> $protectedPrefixes
     */
    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        #[Autowire(['/api/admin', '/api/auth'])]
        private array $protectedPrefixes,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || $request->isMethodSafe()) {
            return;
        }

        $path = $request->getPathInfo();
        foreach ($this->protectedPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $token = $request->headers->get(self::HEADER);
                if (!\is_string($token) || !$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
                    throw CsrfTokenInvalid::create();
                }

                return;
            }
        }
    }
}
