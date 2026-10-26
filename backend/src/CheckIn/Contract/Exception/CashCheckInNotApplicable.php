<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * Cash at the desk settles exactly one situation: the participant is unpaid and nothing else stands in the way (CHK-08).
 */
#[AsProblem(code: 'cash_check_in_not_applicable', status: 409)]
final class CashCheckInNotApplicable extends DomainException
{
    public function __construct(public readonly ?CheckInSituation $situation)
    {
        parent::__construct('Cash can be taken at the desk only for an unpaid participant who may otherwise check in.');
    }

    public function context(): array
    {
        return ['situation' => $this->situation?->value];
    }
}
