<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Catalog\Domain\Structure\EventRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * EVT-10: payment methods, due date, hold time, late payment and cancellation rules of one event. Whether the chosen
 * methods can work (a transfer needs Pairrot) is checked when the event is published.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ConfigurePaymentRulesHandler
{
    public function __construct(
        private EventRepository $events,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ConfigurePaymentRules $command): void
    {
        $event = $this->events->get($command->eventId);
        $event->assertVersion($command->expectedVersion);
        $event->changePaymentRules($command->rules->toRules(), \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
