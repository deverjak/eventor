<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;

/**
 * The configuration of a requirement breaks a rule (LEG-06): a voluntary consent cannot be mandatory, pre-checked or irrevocable.
 */
#[AsProblem(code: 'invalid_requirement', status: 422)]
final class InvalidRequirement extends DomainException implements ProvidesViolations
{
    /**
     * @param list<array{path: string, message: string, code: string}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct('The legal requirement is not valid: '.implode(' ', array_column($violations, 'message')));
    }

    public function violations(): array
    {
        return $this->violations;
    }
}
