<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Who a message goes to (chapter 04, path 10; COM-02..04). Registration answers about its own data only: segments
 * on finance, check-in or consent are narrowed by Communication through `FinanceQuery`, `CheckInQuery` and
 * `ConsentQuery`. Contacts are read at send time and never copied onto the bus.
 */
interface RecipientQuery
{
    /**
     * One member per orderer and activity ("one delivery per orderer and activity", dedupe key `campaign:<id>:order:<id>`).
     *
     * @return list<AudienceMember>
     */
    public function resolveAudience(OrganizerId $organizer, AudienceCriteria $criteria): array;

    /**
     * Re-evaluated just before sending (COM-02, `RecipientStillEligible`): a participant who cancelled meanwhile no
     * longer matches.
     */
    public function stillInAudience(OrganizerId $organizer, AudienceCriteria $criteria, AudienceMember $member): bool;

    /**
     * Contact and participants of an order for transactional messages.
     *
     * @throws OrderNotFound
     */
    public function forOrder(OrganizerId $organizer, OrderId $order): RecipientContext;
}
