<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\CheckInId;

/**
 * The check-in was voided already (CHK-07); a voided row cannot be voided or corrected again.
 */
#[AsProblem(code: 'check_in_already_voided', status: 409)]
final class CheckInAlreadyVoided extends DomainException
{
    public static function forId(CheckInId $id): self
    {
        return new self(\sprintf('Check-in "%s" was voided already.', $id->toString()));
    }
}
