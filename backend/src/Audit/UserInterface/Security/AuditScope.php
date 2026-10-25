<?php

declare(strict_types=1);

namespace Eventor\Audit\UserInterface\Security;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * What a user wants to read from the audit log: the whole organizer (`entityType` null) or one kind of entity.
 * The subject of the `audit.view` vote.
 */
final readonly class AuditScope
{
    public const string ATTRIBUTE = 'audit.view';

    public function __construct(
        public OrganizerId $organizer,
        public ?string $entityType = null,
    ) {}
}
