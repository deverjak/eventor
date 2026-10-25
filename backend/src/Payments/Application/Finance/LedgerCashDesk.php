<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Finance;

use Eventor\Payments\Application\Port\OrganizerPaymentPolicy;
use Eventor\Payments\Contract\CashDesk;
use Eventor\Payments\Contract\CashReceipt;
use Eventor\Payments\Contract\Exception\CashNotAllowed;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Audit\ActorType;
use Eventor\Shared\Domain\Id\CheckInStationId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The cash facade for CheckIn (`RecordCashAndCheckIn`): runs in the caller's transaction. A signed-in team member
 * is authorised by CheckIn's permission voter (cash.record, conditional on the policy for the CHECK_IN role); a
 * device token has no role, so for devices the organizer policy `check_in_may_record_cash` decides here.
 */
#[AsAlias(CashDesk::class)]
final readonly class LedgerCashDesk implements CashDesk
{
    public function __construct(
        private CashRegister $register,
        private OrganizerPaymentPolicy $policy,
        private AuditContext $audit,
    ) {}

    public function recordCash(OrganizerId $organizer, OrderId $order, Money $amount, ?CheckInStationId $station, string $idempotencyKey, ?string $note = null): CashReceipt
    {
        if (ActorType::Device === $this->audit->actor()?->type && !$this->policy->checkInMayRecordCash($organizer)) {
            throw CashNotAllowed::forPolicy();
        }

        return $this->register->record($organizer, $order, $amount, $station, $idempotencyKey, $note);
    }
}
