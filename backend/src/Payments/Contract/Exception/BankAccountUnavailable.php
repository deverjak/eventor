<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The bank account is already connected to another subtenant (Pairrot `409 account_unavailable`).
 */
#[AsProblem(code: 'bank_account_unavailable', status: 409)]
final class BankAccountUnavailable extends DomainException
{
    public static function alreadyConnected(): self
    {
        return new self('This bank account is already connected elsewhere.');
    }
}
