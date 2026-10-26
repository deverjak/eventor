<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query\View;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * One item of the previewed selection with the person fields and the per-participation legal requirements that apply to it.
 */
final readonly class PreviewItemView
{
    /**
     * @param list<PreviewFieldView>       $fields
     * @param list<PreviewRequirementView> $requirements
     */
    public function __construct(
        public string $itemRef,
        public ActivityId $activityId,
        public VariantId $variantId,
        public bool $minor,
        public array $fields,
        public array $requirements,
    ) {}
}
