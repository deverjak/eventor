<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\MissingConfirmation;
use Eventor\Payments\Contract\Exception\OrderFinanceNotFound;
use Eventor\Payments\Contract\FinanceQuery;
use Eventor\Payments\Contract\PaymentInstructionsView;
use Eventor\Registration\Application\Query\View\MissingConfirmationItem;
use Eventor\Registration\Application\Query\View\PaymentInstructionView;
use Eventor\Registration\Application\Query\View\SelfServiceOrderView;
use Eventor\Registration\Application\Query\View\SelfServiceParticipationView;
use Eventor\Registration\Application\SelfService\HmacParticipationQrTokens;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Finance\FinancialState;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Psr\Clock\ClockInterface;

/**
 * The self-service page (REG-08): the order behind the token with money, instructions, QR codes and open confirmations.
 */
final readonly class SelfServiceOrderQuery
{
    public function __construct(
        private Connection $connection,
        private OfferLabels $labels,
        private EventCatalog $catalog,
        private FinanceQuery $finance,
        private ConsentQuery $consents,
        private HmacParticipationQrTokens $qr,
        private ClockInterface $clock,
    ) {}

    public function forOrder(OrganizerId $organizer, OrderId $order): SelfServiceOrderView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT event_id, number, orderer_first_name, orderer_last_name, orderer_email, payment_method, amount_due_minor, amount_due_currency
               FROM registration.orders WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $order->toString()],
        );
        if (false === $row) {
            throw OrderNotFound::forId($order);
        }

        $event = EventId::fromString(Cells::string($row['event_id']));
        $currency = Currency::from(Cells::string($row['amount_due_currency']));
        $amountDue = Money::of(Cells::int($row['amount_due_minor']), $currency);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $eventName = '';
        $startsAt = null;
        $allowed = false;
        $deadline = null;
        try {
            $offer = $this->catalog->registrationSnapshot($organizer, $event);
            $eventName = $offer->name;
            $startsAt = $offer->startsAt;
            $deadline = $offer->selfServiceCancellationDeadline;
            $allowed = $offer->selfServiceCancellationAllowed && $offer->endsAt > $now && (null === $deadline || $deadline >= $now);
        } catch (EventNotFound) {
        }

        try {
            $money = $this->finance->forOrder($organizer, $order)->summary;
            $state = $money->state;
            $received = $money->received;
            try {
                $instructions = $this->finance->paymentInstructions($organizer, $order);
            } catch (\AssertionError|\TypeError) {
                // A request that Pairrot issued without an account number (an IBAN-only account): Payments' view cannot be
                // built; the page shows "instructions pending" rather than failing. Reported to the coordinator.
                $instructions = [];
            }
        } catch (OrderFinanceNotFound) {
            $state = FinancialState::Unpaid;
            $received = Money::zero($currency);
            $instructions = [];
        }
        $outstanding = $amountDue->subtract($received);
        $outstanding = $outstanding->isNegative() ? Money::zero($currency) : $outstanding;
        $method = PaymentMethod::from(Cells::string($row['payment_method']));

        /** @var list<array<string, mixed>> $people */
        $people = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.person_id, p.activity_id, p.variant_id, p.state, p.legal_state, p.answers_state, p.hold_expires_at, p.registration_code, p.price_minor, p.price_currency, pe.first_name, pe.last_name
               FROM registration.participation p JOIN registration.person pe ON pe.id = p.person_id
              WHERE p.order_id = :id ORDER BY p.created_at, p.id',
            ['id' => $order->toString()],
        );
        $missing = $this->consents->missingFor($organizer, array_map(static fn(array $p): ParticipationId => ParticipationId::fromString(Cells::string($p['id'])), $people));

        $participations = array_map(function (array $p) use ($organizer, $event, $missing, $allowed): SelfServiceParticipationView {
            $id = ParticipationId::fromString(Cells::string($p['id']));
            $state = ParticipationState::from(Cells::string($p['state']));
            $activity = ActivityId::fromString(Cells::string($p['activity_id']));
            $variant = VariantId::fromString(Cells::string($p['variant_id']));
            $active = \in_array($state, [ParticipationState::Pending, ParticipationState::Confirmed, ParticipationState::RequiresAttention], true);

            return new SelfServiceParticipationView(
                $id,
                PersonId::fromString(Cells::string($p['person_id'])),
                trim(Cells::string($p['first_name'] ?? '').' '.Cells::string($p['last_name'] ?? '')),
                $activity,
                $this->labels->activityName($organizer, $event, $activity),
                $this->labels->activityStart($organizer, $event, $activity),
                $variant,
                $this->labels->variantName($organizer, $event, $variant),
                $state,
                LegalState::from(Cells::string($p['legal_state'])),
                AnswersState::from(Cells::string($p['answers_state'])),
                Cells::nullableTime($p['hold_expires_at']),
                Cells::string($p['registration_code']),
                Money::of(Cells::int($p['price_minor']), Currency::from(Cells::string($p['price_currency']))),
                $active ? $this->qr->issue($organizer, $id) : null,
                array_map(static fn(MissingConfirmation $m): MissingConfirmationItem => new MissingConfirmationItem($m->requirementId, $m->documentTitle, $m->mandatory, $m->reconfirmation), $missing[$id->toString()] ?? []),
                $active && $allowed,
            );
        }, $people);

        $name = trim(Cells::string($row['orderer_first_name'] ?? '').' '.Cells::string($row['orderer_last_name'] ?? ''));

        return new SelfServiceOrderView(
            $order,
            Cells::string($row['number']),
            $event,
            $eventName,
            $startsAt,
            $name,
            Cells::string($row['orderer_email']),
            $method,
            $amountDue,
            $state,
            $received,
            $outstanding,
            PaymentMethod::BankTransfer === $method && [] === $instructions && $outstanding->isPositive(),
            array_map(static fn(PaymentInstructionsView $i): PaymentInstructionView => new PaymentInstructionView($i->variableSymbol, $i->accountNumber, $i->iban, $i->amount, $i->dueAt, $i->spaydPayload, $i->message), $instructions),
            $allowed,
            $deadline,
            $participations,
        );
    }
}
