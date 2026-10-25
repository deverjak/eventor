<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\LegalRequirementId;

/**
 * No such requirement in this organizer.
 */
#[AsProblem(code: 'legal_requirement_not_found', status: 404)]
final class RequirementNotFound extends DomainException
{
    public static function forId(LegalRequirementId $id): self
    {
        return new self(\sprintf('Legal requirement "%s" was not found.', $id->toString()));
    }
}
