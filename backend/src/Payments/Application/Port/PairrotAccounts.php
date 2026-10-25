<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Mapping organizer <-> Pairrot subtenant and bank connection (ADR 004: ids only, not secret). Payments keeps it
 * because the webhooks identify the tenant by the Pairrot id and requests need the connection id.
 */
interface PairrotAccounts
{
    public function find(OrganizerId $organizer): ?PairrotAccount;

    public function findBySubtenant(string $subtenantId): ?PairrotAccount;

    public function saveSubtenant(OrganizerId $organizer, string $subtenantId, string $externalId): void;

    public function saveConnection(OrganizerId $organizer, string $bankConnectionId, string $maskedIban, ?string $inboundAddress): void;
}
