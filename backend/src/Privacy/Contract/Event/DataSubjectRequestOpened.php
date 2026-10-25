<?php

declare(strict_types=1);

namespace Eventor\Privacy\Contract\Event;

use Eventor\Privacy\Contract\DataSubjectRequestType;
use Eventor\Shared\Domain\Id\DataSubjectRequestId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A data subject request (access, export, erasure, rectification) was opened (PRV-03); `dueAt` is the legal deadline.
 *
 * P1. Planned subscribers: attention.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'privacy.data_subject_request_opened', version: 1)]
#[PendingSubscribers('attention')]
final readonly class DataSubjectRequestOpened
{
    public function __construct(
        public OrganizerId $organizerId,
        public DataSubjectRequestId $requestId,
        public DataSubjectRequestType $type,
        public \DateTimeImmutable $dueAt,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
