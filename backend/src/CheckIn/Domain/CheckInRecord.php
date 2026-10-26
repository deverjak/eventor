<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Domain;

use Doctrine\ORM\Mapping as ORM;
use Eventor\CheckIn\Contract\Exception\CheckInAlreadyVoided;
use Eventor\CheckIn\Domain\Event\CheckedIn;
use Eventor\CheckIn\Domain\Event\CheckInWasVoided;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Tenant\TenantScoped;

/**
 * One arrival (CHK-05). The table `check_in` is append-only apart from the void columns (CHK-07): a correction voids
 * the row and inserts a new one that points back (`correctsId`). A partial unique index guarantees one valid row per
 * participation, whatever races at the desk (CHK-06, S10).
 */
#[ORM\Entity]
#[ORM\Table(name: 'check_in', schema: 'checkin')]
class CheckInRecord implements TenantScoped, RecordsEventsInterface
{
    use RecordsEvents {
        record as private recordEvent;
    }

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $voidedAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $voidedBy = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $voidReason = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'check_in_id')]
        private CheckInId $id,
        #[ORM\Column(type: 'organizer_id')]
        private OrganizerId $organizerId,
        #[ORM\Column(type: 'participation_id')]
        private ParticipationId $participationId,
        #[ORM\Column(type: 'check_in_list_id')]
        private CheckInListId $listId,
        #[ORM\Column(type: 'check_in_station_id', nullable: true)]
        private ?CheckInStationId $stationId,
        #[ORM\Column(type: 'string', length: 64, nullable: true)]
        private ?string $checkedInBy,
        #[ORM\Column(type: 'string', length: 100, nullable: true)]
        private ?string $deviceLabel,
        #[ORM\Column(type: 'datetimetz_immutable')]
        private \DateTimeImmutable $checkedInAt,
        #[ORM\Column(type: 'bigint', nullable: true)]
        private ?int $cashMinor,
        #[ORM\Column(type: 'ledger_entry_id', nullable: true)]
        private ?LedgerEntryId $ledgerEntryId,
        #[ORM\Column(type: 'check_in_id', nullable: true)]
        private ?CheckInId $correctsId,
    ) {}

    /**
     * A new arrival. `$by` is the account id of the operator, `$deviceLabel` the label of the device token (one of the two).
     * `$cashMinor` / `$ledgerEntryId` are set when the cash was taken in the same step (CHK-08).
     */
    public static function record(OrganizerId $organizer, ParticipationId $participation, CheckInListId $list, ?CheckInStationId $station, ?string $by, ?string $deviceLabel, \DateTimeImmutable $now, ?int $cashMinor = null, ?LedgerEntryId $ledgerEntryId = null): self
    {
        $record = new self(CheckInId::generate(), $organizer, $participation, $list, $station, $by, $deviceLabel, $now, $cashMinor, $ledgerEntryId, null);
        $record->recordEvent(new CheckedIn($organizer, $record->id, $participation, $list, $station, $now));

        return $record;
    }

    /**
     * The replacement of a row about to be voided (CHK-07). It is the same arrival, so there is no `CheckedIn` event:
     * the void carries `replacedBy`.
     */
    public function replacement(?CheckInStationId $station, ?\DateTimeImmutable $checkedInAt, ?string $by, ?string $deviceLabel): self
    {
        return new self(CheckInId::generate(), $this->organizerId, $this->participationId, $this->listId, $station ?? $this->stationId, $by, $deviceLabel, $checkedInAt ?? $this->checkedInAt, $this->cashMinor, $this->ledgerEntryId, $this->id);
    }

    public function id(): CheckInId
    {
        return $this->id;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function participationId(): ParticipationId
    {
        return $this->participationId;
    }

    public function listId(): CheckInListId
    {
        return $this->listId;
    }

    public function stationId(): ?CheckInStationId
    {
        return $this->stationId;
    }

    public function checkedInBy(): ?string
    {
        return $this->checkedInBy;
    }

    public function deviceLabel(): ?string
    {
        return $this->deviceLabel;
    }

    public function checkedInAt(): \DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function cashMinor(): ?int
    {
        return $this->cashMinor;
    }

    public function isValid(): bool
    {
        return null === $this->voidedAt;
    }

    /**
     * Voids the row (CHK-07). Audited with the reason; `$replacedBy` is the row that takes over, if any.
     *
     * @throws CheckInAlreadyVoided
     */
    public function void(string $by, string $reason, \DateTimeImmutable $now, ?CheckInId $replacedBy = null): void
    {
        if (null !== $this->voidedAt) {
            throw CheckInAlreadyVoided::forId($this->id);
        }
        $this->voidedAt = $now;
        $this->voidedBy = $by;
        $this->voidReason = $reason;
        $this->recordEvent(new CheckInWasVoided($this->organizerId, $this->id, $this->participationId, $this->listId, $by, $reason, $replacedBy, $now));
    }
}
