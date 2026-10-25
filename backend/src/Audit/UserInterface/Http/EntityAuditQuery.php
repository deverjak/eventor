<?php

declare(strict_types=1);

namespace Eventor\Audit\UserInterface\Http;

use Eventor\Audit\Contract\AuditActorKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EntityAuditQuery
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $entityType,
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $entityId,
        public ?AuditActorKind $actor = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 200)]
        public int $perPage = 50,
    ) {}
}
