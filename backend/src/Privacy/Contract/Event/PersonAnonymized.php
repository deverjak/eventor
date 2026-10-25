<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract\Event;

use Eventor\Privacy\Contract\RetentionClass;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A person was anonymized (PRV-04). `personRef` is the id of the (now anonymous) person row.
 *
 * P1. Planned subscribers: reporting.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'privacy.person_anonymized', version: 1)]
#[PendingSubscribers('reporting')]
final readonly class PersonAnonymized
{
    /**
     * @param list<OrderId> $orderIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public PersonId $personRef,
        public RetentionClass $retentionClass,
        public array $orderIds,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
