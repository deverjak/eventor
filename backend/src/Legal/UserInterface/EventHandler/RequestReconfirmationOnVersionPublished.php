<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\EventHandler;

use Eventor\Legal\Application\RequestReconfirmation\RequestReconfirmation;
use Eventor\Legal\Contract\Event\DocumentVersionPublished;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S15: a version flagged "requires reconfirmation" asks everybody who confirmed an older version to confirm again.
 * Without the flag (S14) nothing happens: old registrations stay bound to the version they confirmed.
 */
#[AsMessageHandler(fromTransport: 'legal_events')]
final readonly class RequestReconfirmationOnVersionPublished
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(DocumentVersionPublished $event): void
    {
        if (!$event->requiresReconfirmation) {
            return;
        }

        $this->commands->dispatch(
            new RequestReconfirmation($event->documentId->toString(), $event->versionId->toString()),
            TenantStamp::of($event->organizerId),
        );
    }
}
