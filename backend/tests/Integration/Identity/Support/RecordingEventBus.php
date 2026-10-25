<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity\Support;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Test-only decorator of `event.bus` (registered by Identity's services.php in the test environment): it records every
 * message that is dispatched, then forwards it. Integration events whose subscribers are still `#[PendingSubscribers]`
 * have no transport yet and would otherwise leave no trace. The record is static so that it survives the kernel
 * reboots of the HTTP test client.
 */
final class RecordingEventBus implements MessageBusInterface
{
    /** @var list<object> */
    private static array $messages = [];

    public function __construct(private readonly MessageBusInterface $inner) {}

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        self::$messages[] = $message instanceof Envelope ? $message->getMessage() : $message;

        return $this->inner->dispatch($message, $stamps);
    }

    public static function reset(): void
    {
        self::$messages = [];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public static function dispatched(string $class): array
    {
        return array_values(array_filter(self::$messages, static fn(object $m): bool => $m instanceof $class));
    }
}
