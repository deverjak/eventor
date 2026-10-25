<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\EventPublisher;

use Eventor\Legal\Contract\Event\DocumentVersionPublished;
use Eventor\Legal\Domain\Event\VersionPublished;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Translates the domain event into `legal.document_version_published`; it runs inside the command transaction, so
 * the outbox row commits with the published version.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class PublishDocumentVersionPublished
{
    public function __construct(
        private MessageBusInterface $eventBus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(VersionPublished $event): void
    {
        $this->eventBus->dispatch(
            new DocumentVersionPublished(
                $event->organizerId,
                $event->documentId,
                $event->versionId,
                $event->previousVersionId,
                $event->level,
                Uuid::fromString($event->scopeId),
                $event->requiresReconfirmation,
                $this->clock->now(),
            ),
            [TenantStamp::of($event->organizerId)],
        );
    }
}
