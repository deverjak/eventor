<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Open;

use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Opens an item for a cause unless one is already unresolved (dedupe: one open item per type and subject).
 */
final readonly class OpenAttentionItem
{
    /**
     * @param array<string, scalar|null> $payload neutral context for the UI (no personal data)
     */
    public function __construct(
        public AttentionType $type,
        public ?OrganizerId $organizerId,
        public string $subjectType,
        public string $subjectId,
        public ?EventId $eventId = null,
        public array $payload = [],
    ) {}

    public static function dedupeKey(AttentionType $type, string $subjectType, string $subjectId): string
    {
        return \sprintf('%s:%s:%s', $type->value, $subjectType, $subjectId);
    }
}
