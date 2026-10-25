<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Eventor\Shared\Application\Idempotency\IdempotencyStore;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Shared\Application\Messaging\Stamp\ReplayedStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Makes a command replay-safe when it carries an IdempotencyStamp (chapter 08): same key + same request hash returns
 * the stored result without running the handler, same key + other hash is a 409. It sits OUTSIDE
 * doctrine_transaction, so claiming the key is its own short statement and a failed command frees the key.
 * The command result (HandledStamp) must be JSON-serialisable: arrays, scalars, null or JsonSerializable.
 */
final readonly class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct(private IdempotencyStore $store) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(IdempotencyStamp::class);
        if (null === $stamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        $stored = $this->store->begin($stamp->scope, $stamp->key, $stamp->requestHash);
        if (null !== $stored) {
            return $envelope->with(new HandledStamp($stored->value, 'idempotent-replay'), new ReplayedStamp());
        }

        try {
            $envelope = $stack->next()->handle($envelope, $stack);
        } catch (\Throwable $e) {
            $this->store->abandon($stamp->scope, $stamp->key);

            throw $e;
        }

        $this->store->complete($stamp->scope, $stamp->key, $envelope->last(HandledStamp::class)?->getResult());

        return $envelope;
    }
}
