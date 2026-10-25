<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Exception;

use Eventor\Catalog\Contract\FormViolation;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;

/**
 * The answers do not satisfy the form (FRM-05, FRM-07). `violations()` lists every offending field so the UI can mark it.
 * ProblemFactory exposes the list as the problem `violations` (ProvidesViolations).
 */
#[AsProblem(code: 'form_invalid', status: 422)]
final class FormValidationFailed extends DomainException implements ProvidesViolations
{
    /**
     * @param list<FormViolation> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct(\sprintf('%d answer(s) are invalid.', \count($violations)));
    }

    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public function violations(): array
    {
        return array_map(static fn(FormViolation $v): array => ['path' => $v->path, 'message' => $v->message, 'code' => $v->code], $this->violations);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['violationCount' => \count($this->violations)];
    }
}
