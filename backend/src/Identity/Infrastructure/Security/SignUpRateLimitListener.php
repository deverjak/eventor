<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Throttles the anonymous account-creating endpoints (sign-up, invitation preview and acceptance) per client
 * address. It runs before the payload is read, so invalid requests count as well.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class SignUpRateLimitListener
{
    public const array ROUTES = ['api_auth_sign_up', 'api_auth_invitation_preview', 'api_auth_invitation_accept'];

    public function __construct(
        #[Autowire(service: 'limiter.identity_signup')]
        private RateLimiterFactoryInterface $limiter,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !\in_array($request->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }

        $limit = $this->limiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp());

            throw new TooManyRequestsHttpException($retryAfter, 'Too many attempts. Try again later.');
        }
    }
}
