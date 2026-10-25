<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Schedule;

use Eventor\Payments\Application\Webhook\ReconcilePairrotEvents;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The scheduled jobs of Payments (chapter 06): the worker consumes `scheduler_payments` together with the other
 * transports (`messenger:consume --all`). Every job is an idempotent sweeper, so a missed or doubled run is harmless.
 */
#[AsSchedule('payments')]
final readonly class PaymentsSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('15 minutes', new ReconcilePairrotEvents()),
        );
    }
}
