<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The absolute session limit of the team session (ADR 006, chapter 08): 8 hours of inactivity are enforced by the
 * session handler (`gc_maxlifetime`), and no session lives longer than 7 days from the sign-in, however active it is.
 * The sign-in time is stored in the session on every successful login (also after sign-up and invitation acceptance,
 * which sign in programmatically); an older session is invalidated and the request continues anonymously (401).
 */
final readonly class AbsoluteSessionLimitListener
{
    public const string SESSION_KEY = 'identity.signed_in_at';
    public const int MAX_LIFETIME_SECONDS = 7 * 86400;

    public function __construct(
        private TokenStorageInterface $tokens,
        private ClockInterface $clock,
    ) {}

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        if ('team' !== $event->getFirewallName() || !$event->getRequest()->hasSession()) {
            return;
        }

        $event->getRequest()->getSession()->set(self::SESSION_KEY, $this->clock->now()->getTimestamp());
    }

    /**
     * Runs right after the firewall (priority 8) has made the token available.
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session->has(self::SESSION_KEY)) {
            // A session from before the limit existed: start counting now rather than logging everyone out.
            if (null !== $this->tokens->getToken()) {
                $session->set(self::SESSION_KEY, $this->clock->now()->getTimestamp());
            }

            return;
        }

        $signedInAt = $session->get(self::SESSION_KEY);
        if (\is_int($signedInAt) && $this->clock->now()->getTimestamp() - $signedInAt > self::MAX_LIFETIME_SECONDS) {
            $session->invalidate();
            $this->tokens->setToken(null);
        }
    }
}
