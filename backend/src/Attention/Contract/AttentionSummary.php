<?php

declare(strict_types=1);

namespace Eventor\Attention\Contract;

use Eventor\Shared\Application\Attention\AttentionType;

/**
 * Open items by type, for the dashboard badge. `byType` is keyed by `AttentionType::value`.
 */
final readonly class AttentionSummary
{
    /**
     * @param array<string, int> $byType
     */
    public function __construct(
        public int $open,
        public array $byType,
    ) {}
}
