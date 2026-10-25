<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * What the registrant selected; decides which fields and conditions apply.
 */
final readonly class FormSelection
{
    /**
     * @param list<ActivityId> $activityIds
     * @param list<VariantId> $variantIds
     */
    public function __construct(
        public array $activityIds,
        public array $variantIds,
    ) {}
}
