<?php

declare(strict_types=1);

namespace Eventor\Attention\Domain\Event;

use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class AttentionItemOpened implements Auditable
{
    public function __construct(
        public AttentionItemId $itemId,
        public ?OrganizerId $organizerId,
        public AttentionType $type,
        public ?string $subjectType,
        public ?string $subjectId,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'attention_item',
            $this->itemId->toString(),
            'attention.opened',
            $this->organizerId,
            after: ['type' => $this->type->value, 'subjectType' => $this->subjectType, 'subjectId' => $this->subjectId],
        );
    }
}
