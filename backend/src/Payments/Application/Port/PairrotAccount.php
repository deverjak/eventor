<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class PairrotAccount
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $subtenantId,
        public string $subtenantExternalId,
        public ?string $bankConnectionId,
        public ?string $maskedIban,
        public ?string $inboundAddress,
    ) {}

    public function canIssueRequests(): bool
    {
        return null !== $this->bankConnectionId;
    }
}
