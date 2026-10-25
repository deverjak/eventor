<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Close;

use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The cause of an item went away by itself (a transaction was allocated, a refund settled, a participation
 * restored): resolve the unresolved item of that type and subject. Nothing to close is fine.
 */
final readonly class CloseAttentionItem
{
    public function __construct(
        public AttentionType $type,
        public ?OrganizerId $organizerId,
        public string $subjectType,
        public string $subjectId,
    ) {}
}
