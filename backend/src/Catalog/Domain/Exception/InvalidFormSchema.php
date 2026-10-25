<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;

/**
 * The submitted form schema is not valid (FRM-01..06): unknown type, duplicate key, a choice field without choices,
 * a condition that points to a missing field, ... `violations()` lists every problem with its path in the schema.
 */
#[AsProblem(code: 'form_schema_invalid', status: 422)]
final class InvalidFormSchema extends DomainException implements ProvidesViolations
{
    /**
     * @param list<array{path: string, message: string, code: string}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct(\sprintf('The form schema has %d problem(s).', \count($violations)));
    }

    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['violationCount' => \count($this->violations)];
    }
}
