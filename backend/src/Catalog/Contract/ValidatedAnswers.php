<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\FormVersionId;

/**
 * Result of a successful validation, one entry per submitted answer set.
 */
final readonly class ValidatedAnswers
{
    /**
     * @param list<ValidatedAnswerSet> $answerSets
     */
    public function __construct(
        public FormVersionId $formVersionId,
        public array $answerSets,
    ) {}
}
