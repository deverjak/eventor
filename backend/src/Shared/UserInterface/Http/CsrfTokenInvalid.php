<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'csrf_invalid', status: 403)]
final class CsrfTokenInvalid extends DomainException
{
    public static function create(): self
    {
        return new self('Missing or invalid CSRF token. Send a random token of at least 24 characters in the "csrf-token" header from the same origin as the application.');
    }
}
