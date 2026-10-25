<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * State of the bank-transfer onboarding (ORG-02, chapter 07). Transfer payments can be enabled for an event only
 * when `canIssueTransferPayments()`. `autoMatching` is false for banks Pairrot cannot read: the QR is generated
 * statelessly and finance allocates by hand.
 */
final readonly class OrganizerPaymentSettings
{
    public function __construct(
        public OrganizerId $organizerId,
        public ?string $pairrotSubtenantId,
        public ?string $pairrotBankConnectionId,
        public ?string $maskedIban,
        public bool $transferEnabled,
        public bool $autoMatching,
    ) {}

    public function canIssueTransferPayments(): bool
    {
        return $this->transferEnabled && null !== $this->pairrotSubtenantId && null !== $this->pairrotBankConnectionId;
    }
}
