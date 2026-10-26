<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Communication\Application\Query\View\DeliveryPage;
use Eventor\Communication\Application\Query\View\DeliveryView;
use Eventor\Communication\Contract\DeliveryState;
use Eventor\Communication\Domain\Exception\DeliveryNotFound;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The delivery history (COM-06): per recipient the state, time and error, filterable by campaign, state and order.
 */
final readonly class DeliveryQuery
{
    private const string COLUMNS = 'id, campaign_id, trigger_key, template_key, purpose, state, attempts, last_error, recipient_hint, order_id, activity_id, created_at, send_after, sent_at';

    public function __construct(private Connection $connection) {}

    public function page(OrganizerId $organizer, ?CampaignId $campaign, ?DeliveryState $state, ?OrderId $order, int $page, int $perPage): DeliveryPage
    {
        $where = 'organizer_id = :organizer';
        $params = ['organizer' => $organizer->toString()];
        if (null !== $campaign) {
            $where .= ' AND campaign_id = :campaign';
            $params['campaign'] = $campaign->toString();
        }
        if (null !== $state) {
            $where .= ' AND state = :state';
            $params['state'] = $state->value;
        }
        if (null !== $order) {
            $where .= ' AND order_id = :order';
            $params['order'] = $order->toString();
        }

        $total = Row::int(['n' => $this->connection->fetchOne("SELECT count(*) FROM communication.delivery WHERE $where", $params)], 'n');
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS." FROM communication.delivery WHERE $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return new DeliveryPage(array_map(self::view(...), $rows), $total);
    }

    /**
     * @throws DeliveryNotFound
     */
    public function get(OrganizerId $organizer, string $id): DeliveryView
    {
        /** @var array<string, mixed>|false $row */
        $row = \Eventor\Shared\Domain\Id\AbstractId::isValid($id)
            ? $this->connection->fetchAssociative('SELECT '.self::COLUMNS.' FROM communication.delivery WHERE organizer_id = :organizer AND id = :id', ['organizer' => $organizer->toString(), 'id' => $id])
            : false;

        return false === $row ? throw DeliveryNotFound::forId($id) : self::view($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function view(array $row): DeliveryView
    {
        return new DeliveryView(
            Row::string($row, 'id'),
            Row::nullableString($row, 'campaign_id'),
            Row::string($row, 'trigger_key'),
            Row::string($row, 'template_key'),
            Row::string($row, 'purpose'),
            Row::string($row, 'state'),
            Row::int($row, 'attempts'),
            Row::nullableString($row, 'last_error'),
            Row::nullableString($row, 'recipient_hint'),
            Row::nullableString($row, 'order_id'),
            Row::nullableString($row, 'activity_id'),
            Row::date($row, 'created_at'),
            Row::date($row, 'send_after'),
            Row::nullableDate($row, 'sent_at'),
        );
    }
}
