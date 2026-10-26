<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\Event\DocumentVersionPublished;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S14: a new version of an event's document that nobody has to confirm again still deserves a word. The organizer gets a
 * draft campaign to review and send. (A version that requires reconfirmation is announced by `OnReconfirmationRequested`;
 * organizer- and activity-level documents have no single audience to propose.)
 */
#[AsMessageHandler(fromTransport: 'communication_events')]
final readonly class OnDocumentVersionPublished
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(DocumentVersionPublished $event): void
    {
        if ($event->requiresReconfirmation || DocumentLevel::Event !== $event->level) {
            return;
        }

        $this->commands->dispatch(
            new ProposeCampaign($event->scopeId->toRfc4122(), TemplateKey::DocumentChanged, new CampaignAudienceSpec(), 'document_changed:'.$event->versionId->toString()),
            TenantStamp::of($event->organizerId),
        );
    }
}
