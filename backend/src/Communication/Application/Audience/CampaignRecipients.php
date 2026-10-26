<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Audience;

use Eventor\CheckIn\Contract\CheckInQuery;
use Eventor\Communication\Application\Port\ParticipationFacts;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Domain\Campaign\Campaign;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Registration\Contract\AudienceCriteria;
use Eventor\Registration\Contract\AudienceMember;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\RecipientQuery;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * Targeting (COM-04, PRV-07): Registration answers "who is registered where", Communication narrows by the facts of
 * the other modules (payment state from Payments, arrival from CheckIn, marketing consent from Legal). The marketing
 * restriction is part of this query and cannot be switched off. The same narrowing runs again for one member just
 * before sending (`stillIncluded`).
 */
final readonly class CampaignRecipients
{
    /** The consent a marketing campaign needs: a granted, not withdrawn `consent` requirement with this purpose. */
    public const string MARKETING_PURPOSE = 'marketing';

    public function __construct(
        private RecipientQuery $recipients,
        private FinanceQuery $finance,
        private CheckInQuery $checkIns,
        private ParticipationFacts $participations,
        private ConsentQuery $consents,
    ) {}

    /**
     * @return list<AudienceMember> one per orderer and activity
     */
    public function resolve(OrganizerId $organizer, EventId $event, CampaignAudienceSpec $spec, CampaignPurpose $purpose): array
    {
        $members = $this->recipients->resolveAudience($organizer, $this->criteria($event, $spec));

        return $this->narrow($organizer, $members, $spec, $purpose);
    }

    public function stillIncluded(OrganizerId $organizer, Campaign $campaign, AudienceMember $member): bool
    {
        $spec = $campaign->audience();
        if (!$this->recipients->stillInAudience($organizer, $this->criteria($campaign->eventId(), $spec), $member)) {
            return false;
        }

        return [] !== $this->narrow($organizer, [$member], $spec, $campaign->purpose());
    }

    public function criteria(EventId $event, CampaignAudienceSpec $spec): AudienceCriteria
    {
        $states = [] === $spec->participationStates
            ? [ParticipationState::Pending, ParticipationState::Confirmed, ParticipationState::RequiresAttention]
            : array_map(ParticipationState::from(...), $spec->participationStates);

        return new AudienceCriteria(
            $event,
            array_map(ActivityId::fromString(...), $spec->activityIds),
            array_map(VariantId::fromString(...), $spec->variantIds),
            $states,
        );
    }

    /**
     * @param list<AudienceMember> $members
     *
     * @return list<AudienceMember>
     */
    private function narrow(OrganizerId $organizer, array $members, CampaignAudienceSpec $spec, CampaignPurpose $purpose): array
    {
        if ([] !== $members && [] !== $spec->financialStates) {
            $wanted = array_map(FinancialState::from(...), $spec->financialStates);
            $summaries = $this->finance->summaries($organizer, $this->orderIds($members));
            $members = array_values(array_filter(
                $members,
                static fn(AudienceMember $m): bool => isset($summaries[$m->orderId->toString()]) && \in_array($summaries[$m->orderId->toString()]->state, $wanted, true),
            ));
        }

        if ([] !== $members && null !== $spec->checkedIn) {
            $all = array_merge(...array_map(static fn(AudienceMember $m): array => $m->participationIds, $members));
            $arrived = array_flip(array_map(static fn($id): string => $id->toString(), $this->checkIns->checkedIn($organizer, $all)));
            $members = array_values(array_filter(
                $members,
                static function (AudienceMember $m) use ($arrived, $spec): bool {
                    $any = false;
                    foreach ($m->participationIds as $id) {
                        $any = $any || isset($arrived[$id->toString()]);
                    }

                    return $any === $spec->checkedIn;
                },
            ));
        }

        if ([] !== $members && CampaignPurpose::Marketing === $purpose) {
            $members = $this->withMarketingConsent($organizer, $members);
        }

        return $members;
    }

    /**
     * @param list<AudienceMember> $members
     *
     * @return list<\Eventor\Shared\Domain\Id\OrderId>
     */
    private function orderIds(array $members): array
    {
        $ids = [];
        foreach ($members as $member) {
            $ids[$member->orderId->toString()] = $member->orderId;
        }

        return array_values($ids);
    }

    /**
     * PRV-07: marketing only to people with a granted and not withdrawn consent. A member qualifies when at least one of
     * its participants (persons) has it.
     *
     * @param list<AudienceMember> $members
     *
     * @return list<AudienceMember>
     */
    private function withMarketingConsent(OrganizerId $organizer, array $members): array
    {
        $all = array_merge(...array_map(static fn(AudienceMember $m): array => $m->participationIds, $members));
        $personOf = [];
        foreach ($this->participations->find($organizer, $all) as $view) {
            $personOf[$view->id->toString()] = $view->personId;
        }
        $granted = array_flip(array_map(
            static fn(PersonId $p): string => $p->toString(),
            $this->consents->grantedAmong($organizer, RequirementType::Consent, self::MARKETING_PURPOSE, array_values($personOf)),
        ));

        return array_values(array_filter(
            $members,
            static function (AudienceMember $m) use ($personOf, $granted): bool {
                foreach ($m->participationIds as $id) {
                    $person = $personOf[$id->toString()] ?? null;
                    if (null !== $person && isset($granted[$person->toString()])) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }
}
