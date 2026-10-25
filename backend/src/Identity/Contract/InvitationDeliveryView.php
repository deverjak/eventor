<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Everything a team invitation message needs. Contains the invitee e-mail: use it only to deliver.
 */
final readonly class InvitationDeliveryView
{
    public function __construct(
        public InvitationId $invitationId,
        public OrganizerId $organizerId,
        public string $recipientEmail,
        public string $role,
        public string $organizerName,
        public string $acceptUrl,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
