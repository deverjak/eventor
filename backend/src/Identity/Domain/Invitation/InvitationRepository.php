<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Invitation;

use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;

interface InvitationRepository
{
    public function add(Invitation $invitation): void;

    public function find(InvitationId $id): ?Invitation;

    /**
     * Invitations of this organizer for this e-mail that can still be accepted.
     *
     * @return list<Invitation>
     */
    public function findPending(OrganizerId $organizer, string $email, \DateTimeImmutable $now): array;
}
