<?php

declare(strict_types=1);

namespace Eventor\Attention\Domain\Event;

use Eventor\Attention\Contract\AttentionState;
use Eventor\Shared\Domain\Audit\Auditable;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The item left the queue: resolved (handled, or its cause went away) or dismissed.
 */
final readonly class AttentionItemClosed implements Auditable
{
    public function __construct(
        public AttentionItemId $itemId,
        public ?OrganizerId $organizerId,
        public AttentionState $state,
        public string $resolution,
        public ?string $note,
    ) {}

    public function auditChange(): AuditChange
    {
        return new AuditChange(
            'attention_item',
            $this->itemId->toString(),
            AttentionState::Dismissed === $this->state ? 'attention.dismissed' : 'attention.resolved',
            $this->organizerId,
            after: ['state' => $this->state->value, 'resolution' => $this->resolution],
            reason: $this->note,
        );
    }
}
