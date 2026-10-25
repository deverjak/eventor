<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * A decision to append. `subject` is the person the requirement is about (the child), `decidedBy` the confirming person
 * when different (the guardian). A staff decision needs a note (LEG-07).
 */
final readonly class ConfirmationToRecord
{
    public function __construct(
        public LegalRequirementId $requirementId,
        public DocumentVersionId $versionId,
        public ConsentDecision $decision,
        public PersonId $subject,
        public ?PersonId $decidedBy,
        public ?ParticipationId $participationId,
        public ?OrderId $orderId,
        public ConfirmationChannel $channel,
        public ?string $note,
        public \DateTimeImmutable $decidedAt,
    ) {
        if (null === $participationId && null === $orderId) {
            throw new \InvalidArgumentException('A confirmation belongs to a participation or an order.');
        }
        if (ConfirmationChannel::Staff === $channel && (null === $note || '' === trim($note))) {
            throw new \InvalidArgumentException('A decision recorded by staff needs a note.');
        }
    }
}
