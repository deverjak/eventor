<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;

/**
 * One field of a form schema (ADR 007, `FieldDefinition`). The `key` is stable across versions (renaming the label
 * keeps it), `scope` says to whom the answer belongs (FRM-03), `semantic` makes the system understand it (FRM-04),
 * `activityIds` / `variantIds` bind it to a selection (FRM-06) and `condition` shows it conditionally (FRM-05).
 */
final readonly class FieldDefinition
{
    /**
     * @param array<string, mixed> $constraints type-specific limits (FRM-02), keys per `FieldTypeName::allowedConstraints()`
     * @param list<string>         $activityIds empty = applies to every selection
     * @param list<string>         $variantIds  empty = applies to every selection
     */
    public function __construct(
        public string $key,
        public FieldTypeName $type,
        public string $label,
        public ?string $help,
        public bool $required,
        public AnswerScope $scope,
        public array $constraints = [],
        public ?SemanticField $semantic = null,
        public ?FieldCondition $condition = null,
        public array $activityIds = [],
        public array $variantIds = [],
        public mixed $defaultValue = null,
    ) {}

    /** FRM-06: a field bound to activities or variants is asked only when one of them is selected. */
    public function isBoundTo(SelectionContext $context): bool
    {
        if ([] === $this->activityIds && [] === $this->variantIds) {
            return true;
        }

        return [] !== array_intersect($this->activityIds, $context->activityIds)
            || [] !== array_intersect($this->variantIds, $context->variantIds);
    }

    public function isVisible(SelectionContext $context): bool
    {
        return null === $this->condition || $this->condition->isSatisfiedBy($context);
    }

    public function isApplicable(SelectionContext $context): bool
    {
        return $this->isBoundTo($context) && $this->isVisible($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'help' => $this->help,
            'required' => $this->required,
            'scope' => $this->scope->value,
            'constraints' => $this->constraints,
            'semantic' => $this->semantic?->value,
            'condition' => $this->condition?->toArray(),
            'activityIds' => $this->activityIds,
            'variantIds' => $this->variantIds,
            'defaultValue' => $this->defaultValue,
        ];
    }
}
