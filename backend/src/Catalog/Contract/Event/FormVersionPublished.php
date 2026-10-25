<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract\Event;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

/**
 * A new immutable form version was published after registrations existed (FRM-08, S17). `addedRequiredFieldIds` are the field keys of the schema (not UUIDs).
 *
 * P0. Planned subscribers: registration.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'catalog.form_version_published', version: 1)]
#[PendingSubscribers('registration')]
final readonly class FormVersionPublished
{
    /**
     * @param list<string> $addedRequiredFieldIds
     */
    public function __construct(
        public OrganizerId $organizerId,
        public EventId $eventId,
        public FormVersionId $formVersionId,
        public ?FormVersionId $previousFormVersionId,
        public array $addedRequiredFieldIds,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
