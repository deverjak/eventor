<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

/**
 * What decides which fields of a form apply: the activities and variants the registrant selected (strings of the
 * ids) and the answers of the answer set being validated.
 */
final readonly class SelectionContext
{
    /**
     * @param list<string>         $activityIds
     * @param list<string>         $variantIds
     * @param array<string, mixed> $answers     raw answers by field key
     */
    public function __construct(
        public array $activityIds,
        public array $variantIds,
        public array $answers = [],
    ) {}

    public function withAnswers(array $answers): self
    {
        return new self($this->activityIds, $this->variantIds, $answers);
    }

    public function answer(string $key): mixed
    {
        return $this->answers[$key] ?? null;
    }
}
