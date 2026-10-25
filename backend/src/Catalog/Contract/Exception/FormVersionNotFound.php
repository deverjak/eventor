<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\FormVersionId;

/**
 * No such form version.
 */
#[AsProblem(code: 'form_version_not_found', status: 404)]
final class FormVersionNotFound extends DomainException
{
    public static function forId(FormVersionId $id): self
    {
        return new self(\sprintf('Form version "%s" was not found.', $id->toString()));
    }
}
