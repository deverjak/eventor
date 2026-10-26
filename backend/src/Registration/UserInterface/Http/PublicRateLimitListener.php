<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * API-03: throttles the anonymous order endpoint per client address (10 per 10 minutes) and per address and event
 * (3 per minute) before the payload is read, so invalid requests count as well. The answer is a 429 with `Retry-After`.
 * The link recovery is NOT limited here: it must answer 202 whatever happens, so it consumes its limiters itself.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class PublicRateLimitListener
{
    public const string ORDER_ROUTE = 'api_public_order_create';

    public function __construct(
        #[Autowire(service: 'limiter.registration_order_ip')]
        private RateLimiterFactoryInterface $orderPerIp,
        #[Autowire(service: 'limiter.registration_order_event')]
        private RateLimiterFactoryInterface $orderPerEvent,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || self::ORDER_ROUTE !== $request->attributes->get('_route')) {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $limits = [
            $this->orderPerIp->create($ip)->consume(),
            $this->orderPerEvent->create($ip.'|'.$request->attributes->getString('orgSlug').'|'.$request->attributes->getString('eventSlug'))->consume(),
        ];

        foreach ($limits as $limit) {
            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp()), 'Too many requests. Try again later.');
            }
        }
    }
}
