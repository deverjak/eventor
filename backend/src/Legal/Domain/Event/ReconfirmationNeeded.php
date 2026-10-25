<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Event;

use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Domain event: confirmations of an older version must be repeated for the new one (S15). Translated into
 * `legal.reconfirmation_requested`.
 */
final readonly class ReconfirmationNeeded
{
    /**
     * @param list<ParticipationId> $participationIds
     * @param list<OrderId> $orderIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public LegalRequirementId $requirementId,
        public DocumentVersionId $versionId,
        public array $participationIds,
        public array $orderIds,
    ) {}
}
