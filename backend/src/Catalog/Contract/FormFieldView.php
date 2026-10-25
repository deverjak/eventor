<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * One field of the schema. Empty `activityIds` / `variantIds` = the field applies to every selection.
 */
final readonly class FormFieldView
{
    /**
     * @param list<ActivityId> $activityIds
     * @param list<VariantId> $variantIds
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public AnswerScope $scope,
        public bool $required,
        public ?SemanticField $semantic,
        public array $activityIds,
        public array $variantIds,
    ) {}
}
