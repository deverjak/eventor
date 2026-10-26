<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Contract\Exception;

use Eventor\CheckIn\Contract\CheckInSituation;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The matrix lets the participant in with a warning (for example unpaid), and the operator did not acknowledge it.
 * The desk either takes cash (`POST .../cash-check-ins`) or repeats the request with `acknowledgeWarning: true`.
 */
#[AsProblem(code: 'check_in_warning_unacknowledged', status: 409)]
final class CheckInWarningNotAcknowledged extends DomainException
{
    public function __construct(public readonly CheckInSituation $situation)
    {
        parent::__construct(\sprintf('The participant can check in after the warning "%s" is acknowledged.', $situation->value));
    }

    public function context(): array
    {
        return ['situation' => $this->situation->value];
    }

    public function nextSteps(): array
    {
        return CheckInSituation::Unpaid === $this->situation ? [['action' => 'acknowledge_warning'], ['action' => 'record_cash']] : [['action' => 'acknowledge_warning']];
    }
}
