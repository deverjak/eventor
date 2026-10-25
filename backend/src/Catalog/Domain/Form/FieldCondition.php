<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

/**
 * "Show this field if (activity = X) / (variant = Y) / (answer of field F = Z)" (FRM-05). Evaluated on the server
 * (FRM-07): a field whose condition is not met must not be sent and is never required.
 */
final readonly class FieldCondition
{
    /**
     * @param list<string> $ids      activity or variant ids (any of them selected satisfies the condition)
     * @param ?string      $field    key of the other field for an `answer` condition
     * @param mixed        $value    compared value for `equals` / `not_equals`
     */
    public function __construct(
        public ConditionType $type,
        public array $ids = [],
        public ?string $field = null,
        public ?ConditionOperator $operator = null,
        public mixed $value = null,
    ) {}

    public function isSatisfiedBy(SelectionContext $context): bool
    {
        return match ($this->type) {
            ConditionType::Activity => [] !== array_intersect($this->ids, $context->activityIds),
            ConditionType::Variant => [] !== array_intersect($this->ids, $context->variantIds),
            ConditionType::Answer => $this->answerMatches($context->answer($this->field ?? '')),
        };
    }

    public function dependsOnAnswers(): bool
    {
        return ConditionType::Answer === $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return match ($this->type) {
            ConditionType::Activity, ConditionType::Variant => ['type' => $this->type->value, 'ids' => $this->ids],
            ConditionType::Answer => array_filter(
                ['type' => 'answer', 'field' => $this->field, 'operator' => $this->operator?->value, 'value' => $this->value],
                static fn(mixed $v, string $k): bool => 'value' !== $k || null !== $v,
                \ARRAY_FILTER_USE_BOTH,
            ),
        };
    }

    private function answerMatches(mixed $answer): bool
    {
        $filled = null !== $answer && '' !== $answer && [] !== $answer;

        return match ($this->operator ?? ConditionOperator::Filled) {
            ConditionOperator::Filled => $filled,
            ConditionOperator::Empty => !$filled,
            ConditionOperator::Equals => $this->contains($answer),
            ConditionOperator::NotEquals => !$this->contains($answer),
        };
    }

    private function contains(mixed $answer): bool
    {
        if (\is_array($answer)) {
            foreach ($answer as $item) {
                if (self::same($item, $this->value)) {
                    return true;
                }
            }

            return false;
        }

        return null !== $answer && self::same($answer, $this->value);
    }

    /** Numbers and strings compare by their text ("5" equals 5); booleans and everything else strictly. */
    private static function same(mixed $a, mixed $b): bool
    {
        if (\is_scalar($a) && \is_scalar($b) && !\is_bool($a) && !\is_bool($b)) {
            return (string) $a === (string) $b;
        }

        return $a === $b;
    }
}
