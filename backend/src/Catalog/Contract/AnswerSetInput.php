<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Raw answers of one subject. `subjectRef` is the caller's own reference (the `personRef` of the request),
 * echoed back in the validated result so the caller can attach ids afterwards.
 */
final readonly class AnswerSetInput
{
    /**
     * @param array<string, mixed> $answers
     */
    public function __construct(
        public AnswerScope $scope,
        public string $subjectRef,
        public array $answers,
    ) {}
}
