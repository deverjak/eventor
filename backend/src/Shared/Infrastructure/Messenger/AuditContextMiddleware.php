<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\Stamp\ActorStamp;
use Eventor\Shared\Application\Messaging\Stamp\CorrelationStamp;
use Eventor\Shared\Infrastructure\Audit\ActorResolver;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Gives every unit of work an actor and a correlation id (AUD-02, AUD-03):
 *  - dispatching: the actor (security token, else system) and the correlation id are added as stamps, so they
 *    survive the queue;
 *  - handling: the AuditContext is set from the stamps for the duration of the handler.
 */
final readonly class AuditContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuditContext $context,
        private ActorResolver $actors,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $received = null !== $envelope->last(ReceivedStamp::class);

        $actorStamp = $envelope->last(ActorStamp::class);
        $correlation = $envelope->last(CorrelationStamp::class);

        if (!$received) {
            if (null === $actorStamp) {
                $actorStamp = ActorStamp::of($this->context->actor() ?? $this->actors->current());
                $envelope = $envelope->with($actorStamp);
            }
            if (null === $correlation) {
                $correlation = new CorrelationStamp($this->context->correlationId());
                $envelope = $envelope->with($correlation);
            }
        }

        $previousActor = $this->context->actor();
        $previousCorrelation = $this->context->hasCorrelationId() ? $this->context->correlationId() : null;

        $this->context->start($actorStamp?->actor() ?? $previousActor, $correlation->correlationId ?? $previousCorrelation);
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->context->start($previousActor, $previousCorrelation);
        }
    }
}
