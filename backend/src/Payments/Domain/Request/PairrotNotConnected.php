<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Request;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The organizer has no Pairrot subtenant or bank connection (onboarding ORG-02 was not completed), so no payment request can be issued.
 */
#[AsProblem(code: 'pairrot_not_connected', status: 409)]
final class PairrotNotConnected extends DomainException
{
    public static function create(): self
    {
        return new self('The organizer has not connected a bank account for transfers.');
    }
}
