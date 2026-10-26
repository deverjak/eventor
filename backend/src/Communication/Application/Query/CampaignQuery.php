<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Communication\Application\Audience\CampaignRecipients;
use Eventor\Communication\Application\Query\View\CampaignDetailView;
use Eventor\Communication\Application\Query\View\CampaignPage;
use Eventor\Communication\Application\Query\View\CampaignPreviewView;
use Eventor\Communication\Application\Query\View\CampaignView;
use Eventor\Communication\Contract\CampaignAudienceSpec;
use Eventor\Communication\Contract\CampaignPurpose;
use Eventor\Communication\Contract\CampaignState;
use Eventor\Communication\Contract\Exception\CampaignNotFound;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of the campaign screens (COM-03, COM-06).
 */
final readonly class CampaignQuery
{
    public function __construct(
        private Connection $connection,
        private CampaignRecipients $recipients,
    ) {}

    public function page(OrganizerId $organizer, ?EventId $event, ?CampaignState $state, int $page, int $perPage): CampaignPage
    {
        $where = 'organizer_id = :organizer';
        $params = ['organizer' => $organizer->toString()];
        if (null !== $event) {
            $where .= ' AND event_id = :event';
            $params['event'] = $event->toString();
        }
        if (null !== $state) {
            $where .= ' AND state = :state';
            $params['state'] = $state->value;
        }

        $total = Row::int(['n' => $this->connection->fetchOne("SELECT count(*) FROM communication.campaign WHERE $where", $params)], 'n');
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, event_id, name, purpose, state, scheduled_at, created_at FROM communication.campaign WHERE $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        $counts = $this->counts($organizer, array_map(static fn(array $row): string => Row::string($row, 'id'), $rows));
        $items = array_map(static fn(array $row): CampaignView => new CampaignView(
            Row::string($row, 'id'),
            Row::string($row, 'event_id'),
            Row::string($row, 'name'),
            Row::string($row, 'purpose'),
            Row::string($row, 'state'),
            Row::nullableDate($row, 'scheduled_at'),
            Row::date($row, 'created_at'),
            $counts[Row::string($row, 'id')] ?? [],
        ), $rows);

        return new CampaignPage($items, $total);
    }

    /**
     * @throws CampaignNotFound
     */
    public function detail(OrganizerId $organizer, CampaignId $id): CampaignDetailView
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM communication.campaign WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );
        if (false === $row) {
            throw CampaignNotFound::forId($id);
        }

        return new CampaignDetailView(
            $id->toString(),
            Row::string($row, 'event_id'),
            Row::string($row, 'name'),
            Row::string($row, 'purpose'),
            Row::string($row, 'state'),
            Row::nullableString($row, 'template_key'),
            Row::string($row, 'locale'),
            Row::string($row, 'subject'),
            Row::string($row, 'body'),
            CampaignAudienceSpec::fromArray(Row::json($row, 'audience')),
            Row::nullableDate($row, 'scheduled_at'),
            Row::nullableDate($row, 'started_at'),
            Row::nullableDate($row, 'completed_at'),
            Row::date($row, 'created_at'),
            $this->counts($organizer, [$id->toString()])[$id->toString()] ?? [],
        );
    }

    /**
     * How many deliveries a campaign with this audience would produce right now (COM-03, "počet příjemců").
     */
    public function recipientCount(OrganizerId $organizer, EventId $event, CampaignAudienceSpec $audience, CampaignPurpose $purpose): int
    {
        return \count($this->recipients->resolve($organizer, $event, $audience, $purpose));
    }

    public function preview(OrganizerId $organizer, EventId $event, CampaignAudienceSpec $audience, CampaignPurpose $purpose, string $subject, string $body): CampaignPreviewView
    {
        return new CampaignPreviewView($subject, $body, $this->recipientCount($organizer, $event, $audience, $purpose));
    }

    /**
     * @param list<string> $campaigns
     *
     * @return array<string, array<string, int>> campaign id => state => count
     */
    private function counts(OrganizerId $organizer, array $campaigns): array
    {
        if ([] === $campaigns) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT campaign_id, state, count(*) AS n FROM communication.delivery WHERE organizer_id = :organizer AND campaign_id IN (:campaigns) GROUP BY campaign_id, state ORDER BY campaign_id, state',
            ['organizer' => $organizer->toString(), 'campaigns' => $campaigns],
            ['campaigns' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[Row::string($row, 'campaign_id')][Row::string($row, 'state')] = Row::int($row, 'n');
        }

        return $counts;
    }
}
