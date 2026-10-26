<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The exception matrix refuses the arrival (CHK-09): a cancelled or expired participation, or one that belongs to
 * another activity (another day).
 */
#[AsProblem(code: 'check_in_blocked', status: 409)]
final class CheckInBlocked extends DomainException
{
    public function __construct(public readonly CheckInSituation $situation)
    {
        parent::__construct(\sprintf('The participant cannot check in here (%s).', $situation->value));
    }

    public function context(): array
    {
        return ['situation' => $this->situation->value];
    }
}
