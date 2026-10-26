<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Sending;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\EventSummary;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Contract\Exception\NewsPostNotFound;
use Eventor\Catalog\Contract\NewsReader;
use Eventor\Catalog\Contract\ProgramReader;
use Eventor\Catalog\Contract\ProgramVisibility;
use Eventor\Communication\Application\Port\ParticipationFacts;
use Eventor\Communication\Application\Rendering\DateText;
use Eventor\Communication\Application\Rendering\TemplateVariables;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Contract\InvitationQuery;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Payments\Contract\PaymentInstructionsView;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\OrderQuery;
use Eventor\Registration\Contract\ParticipantRef;
use Eventor\Registration\Contract\RecipientQuery;
use Eventor\Registration\Contract\SelfServiceLinks;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Infrastructure\Formatting\MoneyFormatter;

/**
 * Builds the variables of a message from the read models of the modules that own the facts (chapter 06): organizer,
 * event, order and participants, payment instructions, self-service link, news, program, invitation. Nothing is
 * copied into the delivery; this runs at send time, so the text always shows the current state.
 */
final readonly class MessageContextFactory
{
    public function __construct(
        private OrganizerSettings $organizers,
        private EventCatalog $events,
        private RecipientQuery $recipients,
        private OrderQuery $orders,
        private SelfServiceLinks $links,
        private FinanceQuery $finance,
        private InvitationQuery $invitations,
        private NewsReader $news,
        private ProgramReader $programs,
        private ParticipationFacts $participations,
        private MoneyFormatter $money,
        private DateText $dates,
    ) {}

    /**
     * @throws MessageNotComposable
     */
    public function build(Delivery $delivery): MessageContext
    {
        $organizerId = $delivery->organizerId();
        try {
            $organizer = $this->organizers->contact($organizerId);
        } catch (OrganizerNotFound $e) {
            throw new MessageNotComposable('organizer_gone', $e);
        }

        $variables = TemplateVariables::blank();
        $variables['organizer'] = ['name' => $organizer->name, 'contactEmail' => $organizer->contactEmail];

        if (TemplateKey::MemberInvitation === $delivery->templateKey()) {
            return $this->invitation($delivery, $variables);
        }

        $orderId = $delivery->orderId() ?? throw new MessageNotComposable('no_order');
        try {
            $recipient = $this->recipients->forOrder($organizerId, $orderId);
            $order = $this->orders->get($organizerId, $orderId);
            $event = $this->events->summary($organizerId, $delivery->eventId() ?? $recipient->eventId);
        } catch (OrderNotFound $e) {
            throw new MessageNotComposable('order_gone', $e);
        } catch (EventNotFound $e) {
            throw new MessageNotComposable('event_gone', $e);
        }

        $locale = MessageLocale::fromContact($recipient->contact->locale);
        $zone = $event->timezone;
        $activityId = isset($delivery->params()['allActivities']) ? null : $delivery->activityId();

        $variables['event'] = [
            'name' => $event->name,
            'startsAt' => $this->dates->dateTime($event->startsAt, $zone, $locale),
            'endsAt' => $this->dates->dateTime($event->endsAt, $zone, $locale),
            'venue' => $event->venue->name ?? '',
            'address' => $event->venue->address ?? '',
        ];
        $variables['recipient'] = ['name' => $recipient->contact->displayName];
        $variables['order'] = [
            'number' => $recipient->orderNumber,
            'amountDue' => $this->money->format($order->amountDue),
            'outstanding' => $this->money->format($order->amountDue),
            'holdExpiresAt' => null === $order->holdExpiresAt ? '' : $this->dates->dateTime($order->holdExpiresAt, $zone, $locale),
        ];

        $activityNames = [];
        foreach ($event->activities as $activity) {
            $activityNames[$activity->activityId->toString()] = $activity->name;
            if (null !== $activityId && $activity->activityId->equals($activityId)) {
                $variables['activity'] = [
                    'name' => $activity->name,
                    'startsAt' => $this->dates->dateTime($activity->startsAt ?? $event->startsAt, $zone, $locale),
                ];
            }
        }

        $participants = array_values(array_filter(
            $recipient->participants,
            static fn(ParticipantRef $p): bool => null === $activityId || $p->activityId->equals($activityId),
        ));
        $variables['participants'] = array_map(static fn(ParticipantRef $p): array => [
            'name' => $p->displayName,
            'activity' => $activityNames[$p->activityId->toString()] ?? '',
            'state' => $p->state->value,
        ], $participants);
        $about = $this->participantAbout($delivery, $recipient->participants) ?? ($participants[0] ?? null);
        if (null !== $about) {
            $variables['participant'] = ['name' => $about->displayName, 'activity' => $activityNames[$about->activityId->toString()] ?? ''];
        }

        $variables['selfServiceUrl'] = $this->selfServiceUrl($organizerId, $orderId);
        $qr = $this->payment($delivery, $variables, $zone, $locale);
        $this->refund($delivery, $variables);
        $this->news($delivery, $variables);
        $this->program($delivery, $event, $variables, $zone, $locale);

        return new MessageContext(
            $variables,
            $recipient->contact->email,
            $recipient->contact->displayName,
            $locale,
            $qr,
            $this->consentPersons($delivery, $participants),
        );
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function invitation(Delivery $delivery, array $variables): MessageContext
    {
        try {
            $invitation = $this->invitations->forDelivery($delivery->organizerId(), InvitationId::fromString($delivery->refId() ?? throw new MessageNotComposable('no_invitation')));
        } catch (InvitationNotFound $e) {
            throw new MessageNotComposable('invitation_gone', $e);
        }

        $organizer = $variables['organizer'];
        \assert(\is_array($organizer));
        $organizer['name'] = $invitation->organizerName;
        $variables['organizer'] = $organizer;
        $variables['invitation'] = [
            'organizerName' => $invitation->organizerName,
            'role' => $invitation->role,
            'acceptUrl' => $invitation->acceptUrl,
            'expiresAt' => $this->dates->date($invitation->expiresAt, 'Europe/Prague', MessageLocale::Czech),
        ];

        return new MessageContext($variables, $invitation->recipientEmail, '', MessageLocale::Czech, null, []);
    }

    private function selfServiceUrl(OrganizerId $organizer, OrderId $order): string
    {
        try {
            return $this->links->urlFor($organizer, $order);
        } catch (OrderNotFound) {
            return '';
        }
    }

    /**
     * @param list<ParticipantRef> $participants
     */
    private function participantAbout(Delivery $delivery, array $participants): ?ParticipantRef
    {
        $ref = $delivery->refId();
        if (null === $ref || !ParticipationId::isValid($ref)) {
            return null;
        }
        foreach ($participants as $participant) {
            if ($participant->participationId->toString() === strtolower($ref)) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * Payment block and the QR payload of a message about money; the figures are the current ones.
     *
     * @param array<string, mixed> $variables
     */
    private function payment(Delivery $delivery, array &$variables, string $zone, MessageLocale $locale): ?string
    {
        if (!\in_array($delivery->templateKey(), [TemplateKey::PaymentInstructions, TemplateKey::PaymentReminder, TemplateKey::PaymentPartial, TemplateKey::OrderReceived], true)) {
            return null;
        }
        $organizer = $delivery->organizerId();
        $order = $delivery->orderId();
        \assert(null !== $order);

        try {
            $finance = $this->finance->forOrder($organizer, $order);
            $orderVariables = $variables['order'];
            \assert(\is_array($orderVariables));
            $orderVariables['outstanding'] = $this->money->format($finance->summary->outstanding());
            $variables['order'] = $orderVariables;
        } catch (OrderFinanceNotFound) {
            // free and cash orders have no finance row to show
        }

        $instructions = $this->finance->paymentInstructions($organizer, $order);
        $chosen = null;
        foreach ($instructions as $candidate) {
            if ($candidate->paymentRequestId->toString() === $delivery->refId()) {
                $chosen = $candidate;
            }
        }
        $chosen ??= $instructions[0] ?? null;
        if (!$chosen instanceof PaymentInstructionsView) {
            return null;
        }

        $variables['payment'] = [
            'amount' => $this->money->format($chosen->amount),
            'accountNumber' => $chosen->accountNumber,
            'iban' => $chosen->iban ?? '',
            'variableSymbol' => $chosen->variableSymbol,
            'message' => $chosen->message,
            'dueAt' => $this->dates->date($chosen->dueAt, $zone, $locale),
        ];

        return '' === $chosen->spaydPayload ? null : $chosen->spaydPayload;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function refund(Delivery $delivery, array &$variables): void
    {
        $minor = $delivery->params()['amountMinor'] ?? null;
        $currency = $delivery->params()['currency'] ?? null;
        if (!\is_int($minor) || !\is_string($currency)) {
            return;
        }
        $variables['refund'] = ['amount' => $this->money->format(Money::of($minor, Currency::from($currency)))];
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function news(Delivery $delivery, array &$variables): void
    {
        if (TemplateKey::NewsBroadcast !== $delivery->templateKey() || null === $delivery->refId()) {
            return;
        }
        try {
            $post = $this->news->post($delivery->organizerId(), NewsPostId::fromString($delivery->refId()));
        } catch (NewsPostNotFound $e) {
            throw new MessageNotComposable('news_gone', $e);
        }
        $variables['news'] = ['title' => $post->title, 'body' => $post->body];
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function program(Delivery $delivery, EventSummary $event, array &$variables, string $zone, MessageLocale $locale): void
    {
        if (TemplateKey::ProgramChanged !== $delivery->templateKey()) {
            return;
        }
        $activity = $delivery->activityId();
        $items = [];
        foreach ($this->programs->forEvent($delivery->organizerId(), $event->eventId, ProgramVisibility::Participants) as $item) {
            if (null === $item->activityId || (null !== $activity && $item->activityId->equals($activity))) {
                $items[] = ['title' => $item->title, 'startsAt' => $this->dates->dateTime($item->startsAt, $zone, $locale), 'location' => $item->location ?? ''];
            }
        }
        $variables['program'] = $items;
    }

    /**
     * People whose consent a marketing message relies on: the persons of the targeted participations.
     *
     * @param list<ParticipantRef> $participants
     *
     * @return list<PersonId>
     */
    private function consentPersons(Delivery $delivery, array $participants): array
    {
        if (\Eventor\Communication\Contract\CampaignPurpose::Marketing !== $delivery->purpose()) {
            return [];
        }
        $ids = array_map(static fn(ParticipantRef $p): ParticipationId => $p->participationId, $participants);
        $persons = [];
        foreach ($this->participations->find($delivery->organizerId(), $ids) as $view) {
            $persons[$view->personId->toString()] = $view->personId;
        }

        return array_values($persons);
    }
}
