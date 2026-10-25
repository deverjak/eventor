<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Invitation;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The invited e-mail already has an account: the person signs in first and accepts as that account (ORG-04).
 */
#[AsProblem(code: 'sign_in_required', status: 409)]
final class SignInRequired extends DomainException
{
    public static function create(): self
    {
        return new self('An account with the invited e-mail already exists. Sign in and accept the invitation again.');
    }

    public function nextSteps(): array
    {
        return [['action' => 'sign_in', 'href' => '/api/auth/login']];
    }
}
