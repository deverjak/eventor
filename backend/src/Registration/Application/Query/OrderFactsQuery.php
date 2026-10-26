<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\OrderQuery;
use Eventor\Registration\Contract\OrderView;
use Eventor\Registration\Contract\ParticipationView;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Money;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * An order with its participations for other modules (Attention resolves the event of an item with it, CheckIn and
 * Communication need the participations). The amount due is the current one after cancellations.
 */
#[AsAlias(OrderQuery::class)]
final readonly class OrderFactsQuery implements OrderQuery
{
    public function __construct(
        private Connection $connection,
        private ParticipationFactsQuery $participations,
    ) {}

    public function get(OrganizerId $organizer, OrderId $order): OrderView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, number, event_id, payment_method, amount_due_minor, amount_due_currency, placed_at, form_version_id
               FROM registration.orders WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $order->toString()],
        );
        if (false === $row) {
            throw OrderNotFound::forId($order);
        }

        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT id FROM registration.participation WHERE order_id = :id ORDER BY created_at, id', ['id' => $order->toString()]);
        $views = $this->participations->findMany($organizer, array_map(static fn(string $id): ParticipationId => ParticipationId::fromString($id), $ids));
        $holds = array_filter(array_map(static fn(ParticipationView $p): ?\DateTimeImmutable => $p->holdExpiresAt, $views));

        return new OrderView(
            $order,
            $organizer,
            Cells::string($row['number']),
            EventId::fromString(Cells::string($row['event_id'])),
            PaymentMethod::from(Cells::string($row['payment_method'])),
            Money::of(Cells::int($row['amount_due_minor']), Currency::from(Cells::string($row['amount_due_currency']))),
            Cells::time($row['placed_at']),
            FormVersionId::fromString(Cells::string($row['form_version_id'])),
            [] === $holds ? null : min($holds),
            $views,
        );
    }
}
