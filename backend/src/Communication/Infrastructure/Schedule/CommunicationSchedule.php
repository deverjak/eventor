<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Schedule;

use Eventor\Communication\Application\Scheduling\SweepCommunication;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The scheduled work of Communication (chapter 06: `SendDueCampaigns`, the reminder jobs): one sweeper tick per minute
 * queues the deliveries that fell due (payment and pre-event reminders) and starts the campaigns whose time has come.
 * It walks the current state each time, so a missed or doubled tick is harmless.
 */
#[AsSchedule('communication')]
final readonly class CommunicationSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(RecurringMessage::every('1 minute', new SweepCommunication()));
    }
}
