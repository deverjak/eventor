<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Attention;

use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * "Something needs a human" (ADM-03). `dedupeKey` makes raising idempotent: the same cause never opens two items.
 */
final readonly class AttentionRequest
{
    /**
     * @param array<string, scalar|null> $payload neutral context for the UI (no personal data)
     */
    public function __construct(
        public AttentionType $type,
        public string $dedupeKey,
        public ?OrganizerId $organizerId = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public array $payload = [],
    ) {}
}
