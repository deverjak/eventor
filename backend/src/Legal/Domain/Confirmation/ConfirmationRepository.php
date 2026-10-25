<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Confirmation;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;

interface ConfirmationRepository
{
    /**
     * Appends a row. The row is written at once (not at the next flush) so the next decision of the same chain,
     * possibly in the same call, finds it as its predecessor.
     */
    public function append(Confirmation $confirmation): void;

    /**
     * The latest (not superseded) row of the chain (requirement, subject, participation or order).
     *
     * @return array{id: ConfirmationId, decision: ConsentDecision, orderId: ?OrderId}|null
     */
    public function latestOfChain(LegalRequirementId $requirement, PersonId $subject, ?ParticipationId $participation, ?OrderId $order): ?array;
}
