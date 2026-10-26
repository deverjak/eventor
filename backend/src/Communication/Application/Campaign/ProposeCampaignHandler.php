<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Application\Rendering\TemplateCatalog;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Domain\Campaign\Campaign;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A proposal that already exists (a redelivered system trigger) records nothing; a new one records CampaignChanged.')]
final readonly class ProposeCampaignHandler
{
    public function __construct(
        private CampaignRepository $campaigns,
        private TemplateCatalog $templates,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{campaignId: string, created: bool}
     */
    public function __invoke(ProposeCampaign $command): array
    {
        if (null !== $command->proposalKey && null !== ($existing = $this->campaigns->findByProposalKey($command->proposalKey))) {
            return ['campaignId' => $existing->id()->toString(), 'created' => false];
        }

        $template = $this->templates->resolve($command->templateKey, MessageLocale::Czech);
        $campaign = Campaign::draft(
            CampaignId::generate(),
            $this->tenant->require(),
            EventId::fromString($command->eventId),
            CampaignNames::for($command->templateKey),
            CampaignPurpose::Organizational,
            $command->templateKey,
            MessageLocale::Czech,
            $template->subject,
            $template->body,
            $command->audience,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $command->proposalKey,
            $command->refId,
        );
        $this->campaigns->add($campaign);

        return ['campaignId' => $campaign->id()->toString(), 'created' => true];
    }
}
