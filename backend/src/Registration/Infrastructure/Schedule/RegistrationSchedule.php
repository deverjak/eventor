<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Schedule;

use Eventor\Registration\Application\Expire\ExpireReservations;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The scheduled jobs of Registration (chapter 06): the worker consumes `scheduler_registration` with the other transports.
 * The sweeper is idempotent, so a missed or doubled run is harmless.
 */
#[AsSchedule('registration')]
final readonly class RegistrationSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('1 minute', new ExpireReservations()),
        );
    }
}
