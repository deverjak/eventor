<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Tenant;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantNotSet;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The tenant of the current request or message. Set by CurrentOrganizerResolver (HTTP) and
 * TenantContextMiddleware (Messenger, from the TenantStamp). It is reset between requests, which matters in
 * FrankenPHP worker mode where the container outlives a request (ResetInterface).
 *
 * The Doctrine `organizer` filter is synchronised by TenantFilterSynchronizer, not here: this class is plain state.
 */
final class TenantContext implements ResetInterface
{
    private ?OrganizerId $current = null;

    public function enter(OrganizerId $organizer): void
    {
        $this->current = $organizer;
    }

    public function leave(): void
    {
        $this->current = null;
    }

    public function current(): ?OrganizerId
    {
        return $this->current;
    }

    public function require(): OrganizerId
    {
        return $this->current ?? throw TenantNotSet::forContext();
    }

    public function reset(): void
    {
        $this->current = null;
    }
}
