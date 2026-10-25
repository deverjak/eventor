<?php

declare(strict_types=1);

namespace Eventor\Audit\UserInterface\Http;

use Eventor\Audit\Contract\AuditActorKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OrganizerAuditQuery
{
    public function __construct(
        #[Assert\Length(max: 64)]
        public ?string $entityType = null,
        public ?AuditActorKind $actor = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 200)]
        public int $perPage = 50,
    ) {}
}
