<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;
use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * A mandatory legal requirement has no granted decision (chapter 04, path 2, step 4). `violations()` names the requirements.
 */
#[AsProblem(code: 'missing_confirmation', status: 422)]
final class RequirementNotSatisfied extends DomainException implements ProvidesViolations
{
    /**
     * @param list<LegalRequirementId> $missing
     */
    public function __construct(private readonly array $missing)
    {
        parent::__construct(\sprintf('%d mandatory confirmation(s) are missing.', \count($missing)));
    }

    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public function violations(): array
    {
        return array_map(static fn(LegalRequirementId $id): array => ['path' => 'confirmations.'.$id->toString(), 'message' => 'Confirmation is required.', 'code' => 'missing_confirmation'], $this->missing);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['missingCount' => \count($this->missing)];
    }
}
