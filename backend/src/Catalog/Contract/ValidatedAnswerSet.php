<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Answers in the stored shape `{ "<field_key>": { "t": "<type>", "v": <value> } }`; empty answers are dropped so a
 * blank field never looks answered (FRM-09).
 */
final readonly class ValidatedAnswerSet
{
    /**
     * @param array<string, array{t: string, v: mixed}> $answers
     */
    public function __construct(
        public AnswerScope $scope,
        public string $subjectRef,
        public array $answers,
        public SemanticAnswers $semantic,
    ) {}
}
