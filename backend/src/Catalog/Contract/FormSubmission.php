<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Everything the registrant answered, with the selection that decides which fields apply.
 */
final readonly class FormSubmission
{
    /**
     * @param list<AnswerSetInput> $answerSets
     */
    public function __construct(
        public FormSelection $selection,
        public array $answerSets,
    ) {}
}
