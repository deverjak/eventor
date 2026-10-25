<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Transition;

use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class ResolveAttentionItem
{
    public function __construct(
        public AttentionItemId $itemId,
        public OrganizerId $organizerId,
        public ?string $note = null,
    ) {}
}
