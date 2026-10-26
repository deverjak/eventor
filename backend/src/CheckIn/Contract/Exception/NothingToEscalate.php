<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The participant may check in without any exception, so there is nothing to pass on.
 */
#[AsProblem(code: 'nothing_to_escalate', status: 409)]
final class NothingToEscalate extends DomainException
{
    public function __construct()
    {
        parent::__construct('There is no exception to escalate: the participant can check in.');
    }
}
