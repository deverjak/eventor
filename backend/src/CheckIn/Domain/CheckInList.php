<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

use Doctrine\ORM\Mapping as ORM;
use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\CheckIn\Domain\Event\CheckInListCancelled;
use Eventor\CheckIn\Domain\Event\CheckInListProvisioned;
use Eventor\CheckIn\Domain\Event\CheckInListRescheduled;
use Eventor\CheckIn\Domain\Event\EscalationWasRaised;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * A check-in list (CHK-01): the people expected at one door. By default one list per activity, which is also "per day",
 * since activities are scheduled on days; `opensAt` follows the start of the activity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'check_in_list', schema: 'checkin')]
class CheckInList implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'check_in_list_id')]
        private CheckInListId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'event_id')]
        private EventId $eventId,
        #[ORM\Column(type: 'activity_id')]
        private ActivityId $activityId,
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
        private ?\DateTimeImmutable $opensAt,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $createdAt,
    ) {}

    public static function provision(OrganizerId $organizer, EventId $event, ActivityId $activity, string $name, ?\DateTimeImmutable $opensAt, \DateTimeImmutable $now): self
    {
        $list = new self(CheckInListId::generate(), $organizer, $event, $activity, $name, $opensAt, $now);
        $list->record(new CheckInListProvisioned($organizer, $list->id, $event, $activity, $now));

        return $list;
    }

    public function id(): CheckInListId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function eventId(): EventId
    {
        return $this->eventId;
    }

    public function activityId(): ActivityId
    {
        return $this->activityId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function opensAt(): ?\DateTimeImmutable
    {
        return $this->opensAt;
    }

    public function isOpen(): bool
    {
        return null === $this->cancelledAt;
    }

    /**
     * The activity moved (`catalog.activity_rescheduled`): the list opens at the new start.
     */
    public function reschedule(?\DateTimeImmutable $opensAt, \DateTimeImmutable $now): void
    {
        if ($opensAt?->getTimestamp() === $this->opensAt?->getTimestamp()) {
            return;
        }
        $previous = $this->opensAt;
        $this->opensAt = $opensAt;
        $this->record(new CheckInListRescheduled($this->organizerId, $this->id, $previous, $opensAt, $now));
    }

    /**
     * The event was cancelled: nobody arrives any more.
     */
    public function cancel(\DateTimeImmutable $now): void
    {
        if (null !== $this->cancelledAt) {
            return;
        }
        $this->cancelledAt = $now;
        $this->record(new CheckInListCancelled($this->organizerId, $this->id, $now));
    }

    /**
     * The operator passes a situation of the desk on (CHK-09); Attention opens an item for it.
     */
    public function escalate(ParticipationId $participation, CheckInSituation $situation, \DateTimeImmutable $now): void
    {
        $this->record(new EscalationWasRaised($this->organizerId, $this->id, $participation, $situation, $now));
    }
}
