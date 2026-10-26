<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Eventor\Communication\Application\Audience\CampaignRecipients;
use Eventor\Communication\Application\Campaign\ProposeCampaign;
use Eventor\Communication\Contract\CampaignAudience;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignComposer;
use Eventor\Communication\Contract\CampaignPreview;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\ProposedCampaign;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Exception\InvalidCampaign;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * `CampaignComposer` for the other modules and the admin API: how many deliveries an audience gives, and a draft
 * transactional campaign to confirm (chapter 04, path 10). Sending stays the organizer's decision.
 */
#[AsAlias(CampaignComposer::class)]
final readonly class CampaignComposerQuery implements CampaignComposer
{
    public function __construct(
        private CampaignRecipients $recipients,
        private CommandBus $commands,
    ) {}

    public function preview(OrganizerId $organizer, CampaignAudience $audience): CampaignPreview
    {
        $members = $this->recipients->resolve($organizer, $audience->eventId, self::spec($audience), CampaignPurpose::Organizational);

        return new CampaignPreview(\count($members));
    }

    public function propose(OrganizerId $organizer, ProposedCampaign $campaign): CampaignId
    {
        $key = TemplateKey::tryFrom($campaign->templateKey) ?? throw InvalidCampaign::because(\sprintf('Unknown template "%s".', $campaign->templateKey));
        /** @var array{campaignId: string, created: bool} $result */
        $result = $this->commands->dispatch(
            new ProposeCampaign($campaign->eventId->toString(), $key, self::spec($campaign->audience, $campaign->activityId?->toString())),
            TenantStamp::of($organizer),
        );

        return CampaignId::fromString($result['campaignId']);
    }

    private static function spec(CampaignAudience $audience, ?string $activity = null): CampaignAudienceSpec
    {
        $activities = array_map(static fn($id): string => $id->toString(), $audience->activityIds);
        if (null !== $activity && !\in_array($activity, $activities, true)) {
            $activities[] = $activity;
        }

        return new CampaignAudienceSpec(
            $activities,
            array_map(static fn($id): string => $id->toString(), $audience->variantIds),
            $audience->activeOnly ? [] : array_map(static fn(ParticipationState $state): string => $state->value, ParticipationState::cases()),
            checkedIn: $audience->checkedIn,
        );
    }
}
