<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * Pairrot refused the account number or IBAN (wrong format or check digits, unsupported bank). Not retryable;
 * the onboarding wizard shows it next to the input (ORG-02, step 2).
 */
#[AsProblem(code: 'invalid_bank_account', status: 422)]
final class InvalidBankAccount extends DomainException
{
    public static function because(string $pairrotCode): self
    {
        return new self(\sprintf('The bank account was not accepted (%s).', $pairrotCode));
    }
}
