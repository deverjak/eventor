<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging\Stamp;

use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the organizer of the dispatching context through the queue so the worker can restore the tenant
 * (and with it the Doctrine filter) before the handler runs.
 */
final readonly class TenantStamp implements StampInterface
{
    public function __construct(public string $organizerId) {}

    public static function of(OrganizerId $organizerId): self
    {
        return new self($organizerId->toString());
    }

    public function organizer(): OrganizerId
    {
        return OrganizerId::fromString($this->organizerId);
    }
}
