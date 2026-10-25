<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Identity\Domain\Event\OrganizerPolicyChanged;
use Eventor\Shared\Domain\Event\RecordsEvents;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * `organizer_policy` (chapter 03): 1:1 with the organizer. Payment defaults for new events (due days, reservation TTL,
 * auto-restore of late payments, overpayment handling) and the two configurable exceptions to the permission matrix.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organizer_policy', schema: 'identity')]
class OrganizerPolicy implements RecordsEventsInterface
{
    use RecordsEvents;

    public const int DEFAULT_DUE_DAYS = 14;
    public const int DEFAULT_HOLD_TTL_HOURS = 48;

    #[ORM\Column(type: 'boolean')]
    private bool $checkInMayRecordCash = false;

    #[ORM\Column(type: 'boolean')]
    private bool $eventManagerMayResolvePayments = false;

    #[ORM\Column(type: 'boolean')]
    private bool $autoRestoreLatePayment = false;

    #[ORM\Column(type: 'string', length: 16, enumType: OverpaymentHandling::class)]
    private OverpaymentHandling $overpaymentHandling = OverpaymentHandling::Ask;

    #[ORM\Column(type: 'integer')]
    private int $defaultDueDays = self::DEFAULT_DUE_DAYS;

    #[ORM\Column(type: 'integer')]
    private int $defaultHoldTtlHours = self::DEFAULT_HOLD_TTL_HOURS;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'organizer_id', type: 'organizer_id')]
        private OrganizerId $organizerId,
    ) {}

    public static function defaultsFor(OrganizerId $organizer): self
    {
        return new self($organizer);
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function change(
        bool $checkInMayRecordCash,
        bool $eventManagerMayResolvePayments,
        bool $autoRestoreLatePayment,
        OverpaymentHandling $overpaymentHandling,
        int $defaultDueDays,
        int $defaultHoldTtlHours,
    ): void {
        if ($defaultDueDays < 1 || $defaultDueDays > 365) {
            throw InvalidPolicyValue::of('defaultDueDays', '1 to 365 days');
        }
        if ($defaultHoldTtlHours < 1 || $defaultHoldTtlHours > 720) {
            throw InvalidPolicyValue::of('defaultHoldTtlHours', '1 to 720 hours');
        }

        $before = $this->snapshot();
        $this->checkInMayRecordCash = $checkInMayRecordCash;
        $this->eventManagerMayResolvePayments = $eventManagerMayResolvePayments;
        $this->autoRestoreLatePayment = $autoRestoreLatePayment;
        $this->overpaymentHandling = $overpaymentHandling;
        $this->defaultDueDays = $defaultDueDays;
        $this->defaultHoldTtlHours = $defaultHoldTtlHours;

        $this->record(new OrganizerPolicyChanged($this->organizerId, $before, $this->snapshot()));
    }

    /**
     * @return array<string, scalar>
     */
    public function snapshot(): array
    {
        return [
            'checkInMayRecordCash' => $this->checkInMayRecordCash,
            'eventManagerMayResolvePayments' => $this->eventManagerMayResolvePayments,
            'autoRestoreLatePayment' => $this->autoRestoreLatePayment,
            'overpaymentHandling' => $this->overpaymentHandling->value,
            'defaultDueDays' => $this->defaultDueDays,
            'defaultHoldTtlHours' => $this->defaultHoldTtlHours,
        ];
    }
}
