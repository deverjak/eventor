<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Application\Audience\CampaignRecipients;
use Eventor\Communication\Application\Scheduling\DeliveryScheduler;
use Eventor\Communication\Application\Scheduling\ScheduleDelivery;
use Eventor\Communication\Contract\CampaignState;
use Eventor\Communication\Contract\Exception\CampaignNotFound;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Resolves the audience (ResolveCampaignRecipients, chapter 04 path 10) and plans one delivery per member with the key
 * `campaign:<id>:order:<order>:<activity>`. The sending itself is per delivery on `async_mail`, with the eligibility
 * re-checked just before each send. A campaign without recipients is sent at once.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A campaign that already started (a redelivered system trigger) records nothing; starting one records CampaignChanged.')]
final readonly class StartCampaignHandler
{
    public function __construct(
        private CampaignRepository $campaigns,
        private CampaignRecipients $recipients,
        private DeliveryScheduler $scheduler,
        private WorkflowInterface $campaignStateMachine,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{recipients: int}
     */
    public function __invoke(StartCampaign $command): array
    {
        $id = CampaignId::fromString($command->campaignId);
        $campaign = $this->campaigns->find($id) ?? throw CampaignNotFound::forId($id);
        if ($command->tolerateStarted && \in_array($campaign->getState(), [CampaignState::Sending, CampaignState::Sent], true)) {
            return ['recipients' => 0];
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $campaign->start($this->campaignStateMachine, $now);

        $planned = 0;
        $wholeEvent = [] === $campaign->audience()->activityIds;
        $members = $this->recipients->resolve($campaign->organizerId(), $campaign->eventId(), $campaign->audience(), $campaign->purpose());
        if ($wholeEvent) {
            $members = self::oneDeliveryPerOrder($members);
        }
        foreach ($members as $member) {
            $delivery = $this->scheduler->schedule(new ScheduleDelivery(
                $campaign->templateKey() ?? TemplateKey::Custom,
                \sprintf('campaign:%s:order:%s:%s', $id->toString(), $member->orderId->toString(), $wholeEvent ? 'all' : $member->activityId->toString()),
                eventId: $campaign->eventId()->toString(),
                orderId: $member->orderId->toString(),
                activityId: $member->activityId->toString(),
                refId: $campaign->refId(),
                params: ['participationIds' => implode(',', array_map(static fn($p): string => $p->toString(), $member->participationIds))] + ($wholeEvent ? ['allActivities' => 1] : []),
                purpose: $campaign->purpose(),
            ), $id);
            $planned += null === $delivery ? 0 : 1;
        }

        if (0 === $planned) {
            $campaign->complete($this->campaignStateMachine, 0, 0, $now);
        }

        return ['recipients' => $planned];
    }

    /**
     * A message to the whole event (cancelled event, news, thank-you) reaches an orderer once, not once per activity.
     * The member keeps its first activity (for the eligibility check) and all participations of the order.
     *
     * @param list<\Eventor\Registration\Contract\AudienceMember> $members
     *
     * @return list<\Eventor\Registration\Contract\AudienceMember>
     */
    private static function oneDeliveryPerOrder(array $members): array
    {
        $byOrder = [];
        foreach ($members as $member) {
            $key = $member->orderId->toString();
            $byOrder[$key] = isset($byOrder[$key])
                ? new \Eventor\Registration\Contract\AudienceMember($member->orderId, $byOrder[$key]->activityId, [...$byOrder[$key]->participationIds, ...$member->participationIds])
                : $member;
        }

        return array_values($byOrder);
    }
}
