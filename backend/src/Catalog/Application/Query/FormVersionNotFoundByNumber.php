<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The admin asked for a form version number that the event does not have (same problem as an unknown version id).
 */
#[AsProblem(code: 'form_version_not_found', status: 404)]
final class FormVersionNotFoundByNumber extends DomainException
{
    public function __construct(int $number)
    {
        parent::__construct(\sprintf('Form version %d was not found.', $number));
    }
}
