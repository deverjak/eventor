<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http\Problem;

/**
 * RFC 9457 problem details (`application/problem+json`) with the Eventor extensions `code` (machine-readable),
 * `context`, `nextSteps` (what the client can do next), `violations` (validation) and `correlationId`.
 */
final readonly class Problem
{
    /**
     * @param array<string, scalar|null>                          $context
     * @param list<array{action: string, href?: string}>          $nextSteps
     * @param list<array{path: string, message: string, code?: string}> $violations
     */
    public function __construct(
        public string $code,
        public int $status,
        public string $title,
        public ?string $detail = null,
        public array $context = [],
        public array $nextSteps = [],
        public array $violations = [],
        public ?string $correlationId = null,
    ) {}

    public function type(): string
    {
        return 'https://eventor.cz/problems/'.str_replace('_', '-', $this->code);
    }
}
