<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Sending;

use Eventor\Communication\Application\Audience\CampaignRecipients;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Registration\Contract\AudienceMember;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\OrderQuery;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * COM-02: a message goes out only if the recipient still meets its condition at the moment of sending. A reminder
 * never follows a payment or a cancellation (chapter 05/06: the condition is checked again right before the send, on
 * every attempt). Facts that already happened (a reservation expired, a refund was settled) need no condition.
 */
final readonly class RecipientStillEligible
{
    public function __construct(
        private OrderQuery $orders,
        private FinanceQuery $finance,
        private CampaignRepository $campaigns,
        private CampaignRecipients $audiences,
    ) {}

    public function check(Delivery $delivery): Eligibility
    {
        if (null !== $delivery->campaignId()) {
            return $this->campaignMember($delivery);
        }

        return match ($delivery->templateKey()) {
            TemplateKey::PaymentInstructions, TemplateKey::PaymentReminder => $this->openPayment($delivery),
            TemplateKey::PaymentPartial => $this->partiallyPaid($delivery),
            TemplateKey::ParticipationConfirmed => $this->hasParticipation($delivery, [ParticipationState::Confirmed], null),
            TemplateKey::PreEventReminder => $this->hasParticipation($delivery, [ParticipationState::Confirmed], $delivery->activityId()),
            default => Eligibility::yes(),
        };
    }

    private function campaignMember(Delivery $delivery): Eligibility
    {
        $campaign = $delivery->campaignId() instanceof \Eventor\Shared\Domain\Id\CampaignId ? $this->campaigns->find($delivery->campaignId()) : null;
        $order = $delivery->orderId();
        $activity = $delivery->activityId();
        if (null === $campaign || null === $order || null === $activity) {
            return Eligibility::no('not_in_audience');
        }

        $ids = array_values(array_map(ParticipationId::fromString(...), array_filter(explode(',', (string) ($delivery->params()['participationIds'] ?? '')), static fn(string $id): bool => '' !== $id)));
        $still = $this->audiences->stillIncluded($delivery->organizerId(), $campaign, new AudienceMember($order, $activity, $ids));

        return $still ? Eligibility::yes() : Eligibility::no('not_in_audience');
    }

    /**
     * Instructions and reminders: the order is still unpaid or partly paid, the request is still the active one and
     * somebody is still registered (a cancelled or expired order gets nothing).
     */
    private function openPayment(Delivery $delivery): Eligibility
    {
        $organizer = $delivery->organizerId();
        $order = $delivery->orderId();
        if (null === $order) {
            return Eligibility::no('no_order');
        }

        try {
            $state = $this->finance->forOrder($organizer, $order)->summary->state;
        } catch (OrderFinanceNotFound) {
            return Eligibility::no('no_finance');
        }
        if (FinancialState::Unpaid !== $state && FinancialState::PartiallyPaid !== $state) {
            return Eligibility::no('already_paid');
        }
        if (!$this->hasLiveParticipation($organizer, $order)) {
            return Eligibility::no('no_live_participation');
        }

        $ref = $delivery->refId();
        if (null !== $ref) {
            foreach ($this->finance->paymentInstructions($organizer, $order) as $instructions) {
                if ($instructions->paymentRequestId->toString() === $ref) {
                    return Eligibility::yes();
                }
            }

            return Eligibility::no('request_not_active');
        }

        return Eligibility::yes();
    }

    private function partiallyPaid(Delivery $delivery): Eligibility
    {
        $order = $delivery->orderId();
        if (null === $order) {
            return Eligibility::no('no_order');
        }
        try {
            $state = $this->finance->forOrder($delivery->organizerId(), $order)->summary->state;
        } catch (OrderFinanceNotFound) {
            return Eligibility::no('no_finance');
        }

        return FinancialState::PartiallyPaid === $state ? Eligibility::yes() : Eligibility::no('no_longer_partially_paid');
    }

    /**
     * @param list<ParticipationState> $states
     */
    private function hasParticipation(Delivery $delivery, array $states, ?ActivityId $activity): Eligibility
    {
        $order = $delivery->orderId();
        if (null === $order) {
            return Eligibility::no('no_order');
        }
        try {
            $view = $this->orders->get($delivery->organizerId(), $order);
        } catch (OrderNotFound) {
            return Eligibility::no('order_gone');
        }
        foreach ($view->participations as $participation) {
            if (\in_array($participation->state, $states, true) && (null === $activity || $participation->activityId->equals($activity))) {
                return Eligibility::yes();
            }
        }

        return Eligibility::no('not_confirmed');
    }

    private function hasLiveParticipation(OrganizerId $organizer, OrderId $order): bool
    {
        try {
            $view = $this->orders->get($organizer, $order);
        } catch (OrderNotFound) {
            return false;
        }
        foreach ($view->participations as $participation) {
            if (\in_array($participation->state, [ParticipationState::Pending, ParticipationState::Confirmed, ParticipationState::RequiresAttention], true)) {
                return true;
            }
        }

        return false;
    }
}
