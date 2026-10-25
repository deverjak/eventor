<?php

declare(strict_types=1);

namespace Eventor\Attention\Domain\Event;

use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class AttentionItemAcknowledged implements Auditable
{
    public function __construct(
        public AttentionItemId $itemId,
        public ?OrganizerId $organizerId,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange('attention_item', $this->itemId->toString(), 'attention.acknowledged', $this->organizerId, ['state' => 'open'], ['state' => 'acknowledged']);
    }
}
