<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Both directions of the tenant on the buses (ADR 005):
 *  - dispatching: a message sent while a tenant is active carries a TenantStamp;
 *  - handling (worker, or a command dispatched with an explicit stamp): enter that tenant, switch the Doctrine
 *    `organizer` filter to it and restore the previous state afterwards.
 */
final readonly class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantContext $tenant,
        private TenantFilterSynchronizer $filter,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(TenantStamp::class);

        if (null === $stamp) {
            $current = $this->tenant->current();
            if (null !== $current && null === $envelope->last(ReceivedStamp::class)) {
                $envelope = $envelope->with(TenantStamp::of($current));
            }

            return $stack->next()->handle($envelope, $stack);
        }

        $previous = $this->tenant->current();
        if (null !== $previous && $previous->toString() === $stamp->organizerId) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->tenant->enter($stamp->organizer());
        $this->filter->sync();
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            null === $previous ? $this->tenant->leave() : $this->tenant->enter($previous);
            $this->filter->sync();
        }
    }
}
