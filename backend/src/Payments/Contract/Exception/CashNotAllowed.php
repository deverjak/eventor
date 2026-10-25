<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The organizer policy does not let this actor record cash (`check_in_may_record_cash`, role).
 */
#[AsProblem(code: 'cash_not_allowed', status: 403)]
final class CashNotAllowed extends DomainException
{
    public static function forPolicy(): self
    {
        return new self('Recording cash is not allowed for this role.');
    }
}
