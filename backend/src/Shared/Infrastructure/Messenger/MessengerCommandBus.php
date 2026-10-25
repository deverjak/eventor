<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Eventor\Shared\Application\Messaging\CommandBus;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

#[AsAlias(CommandBus::class)]
final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $bus,
    ) {}

    public function dispatch(object $command, StampInterface ...$stamps): mixed
    {
        try {
            $envelope = $this->bus->dispatch($command, $stamps);
        } catch (HandlerFailedException $e) {
            // Business exceptions are part of the use case contract: let them out unwrapped.
            $wrapped = array_values($e->getWrappedExceptions());
            throw 1 === \count($wrapped) ? $wrapped[0] : $e;
        }

        $handled = $envelope->all(HandledStamp::class);
        if ([] === $handled) {
            throw new \LogicException(\sprintf('No handler handled "%s" on command.bus.', $command::class));
        }

        /** @var HandledStamp $stamp */
        $stamp = end($handled);

        return $stamp->getResult();
    }
}
