<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Confirm;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * REG-09 / S15: the participant confirms what a new version of a document asks (or has not been asked yet), or withdraws a
 * revocable consent (LEG-09). The decision is recorded for the CURRENT version of the requirement.
 */
final readonly class RecordSelfServiceConfirmations
{
    /**
     * @param list<array{requirementId: LegalRequirementId, participationId: ?ParticipationId, decision: ConsentDecision}> $decisions
     */
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public array $decisions,
    ) {}
}
