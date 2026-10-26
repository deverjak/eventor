<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Registration\Contract\Event\AnswersIncomplete;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * COM-01, "výzva k doplnění údajů": once per order and form version, with the self-service link where the answers are filled in.
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnAnswersIncomplete
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(AnswersIncomplete $event): void
    {
        $this->commands->dispatch(new ScheduleDelivery(
            TemplateKey::AnswersIncomplete,
            \sprintf('answers_incomplete:%s:%s', $event->orderId->toString(), $event->formVersionId->toString()),
            orderId: $event->orderId->toString(),
        ), TenantStamp::of($event->organizerId));
    }
}
