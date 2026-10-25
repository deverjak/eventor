<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\ConnectPairrot;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Runs the ORG-02 wizard against Pairrot (through Payments): subtenant, bank account. Both Pairrot calls are
 * idempotent, so a failed or repeated run is safe.
 */
final readonly class ConnectOrganizerToPairrot
{
    public function __construct(
        public OrganizerId $organizerId,
        #[\SensitiveParameter]
        public string $accountNumberOrIban,
        public string $label,
    ) {}
}
