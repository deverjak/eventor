<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Attention\Application\Query\View\AttentionItemPage;
use Eventor\Attention\Contract\AttentionItemView;
use Eventor\Attention\Contract\AttentionState;
use Eventor\Attention\Contract\Exception\AttentionItemNotFound;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of the queue (DBAL, always scoped by the organizer).
 */
final readonly class AttentionItemQuery
{
    private const string COLUMNS = 'id, type, state, subject_type, subject_id, event_id, created_at, payload, acknowledged_at, resolved_at, resolution, resolution_note';

    public function __construct(private Connection $connection) {}

    public function find(OrganizerId $organizer, AttentionItemId $id): ?AttentionItemView
    {
        /** @var array<string, string|null>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::COLUMNS.' FROM attention.attention_item WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );

        return false === $row ? null : self::view($row);
    }

    /**
     * @throws AttentionItemNotFound
     */
    public function get(OrganizerId $organizer, AttentionItemId $id): AttentionItemView
    {
        return $this->find($organizer, $id) ?? throw AttentionItemNotFound::forId($id);
    }

    private static function count(mixed $value): int
    {
        return \is_int($value) || \is_string($value) ? (int) $value : 0;
    }

    /**
     * Newest first. Without a state filter the whole history is listed; pass `AttentionState::Open` etc. to narrow.
     */
    public function page(OrganizerId $organizer, ?AttentionType $type, ?AttentionState $state, ?EventId $event, int $page, int $perPage): AttentionItemPage
    {
        $where = 'organizer_id = :organizer';
        $params = ['organizer' => $organizer->toString()];
        if (null !== $type) {
            $where .= ' AND type = :type';
            $params['type'] = $type->value;
        }
        if (null !== $state) {
            $where .= ' AND state = :state';
            $params['state'] = $state->value;
        }
        if (null !== $event) {
            $where .= ' AND event_id = :event';
            $params['event'] = $event->toString();
        }

        $total = self::count($this->connection->fetchOne("SELECT count(*) FROM attention.attention_item WHERE $where", $params));
        /** @var list<array<string, string|null>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS." FROM attention.attention_item WHERE $where ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset",
            $params + ['limit' => $perPage, 'offset' => ($page - 1) * $perPage],
        );

        return new AttentionItemPage(array_map(self::view(...), $rows), $total);
    }

    /**
     * Unresolved items (open + acknowledged) per type.
     *
     * @return array<string, int> keyed by `AttentionType::value`
     */
    public function unresolvedByType(OrganizerId $organizer, ?EventId $event): array
    {
        $params = ['organizer' => $organizer->toString()];
        $filter = '';
        if (null !== $event) {
            $filter = ' AND event_id = :event';
            $params['event'] = $event->toString();
        }
        /** @var list<array{type: string, n: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT type, count(*) AS n FROM attention.attention_item WHERE organizer_id = :organizer AND state IN ('open', 'acknowledged')$filter GROUP BY type ORDER BY type",
            $params,
        );

        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['type']] = (int) $row['n'];
        }

        return $byType;
    }

    /**
     * @return list<AttentionItemView>
     */
    public function forSubject(OrganizerId $organizer, string $subjectType, string $subjectId): array
    {
        /** @var list<array<string, string|null>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT '.self::COLUMNS.' FROM attention.attention_item WHERE organizer_id = :organizer AND subject_type = :type AND subject_id = :id ORDER BY created_at DESC, id DESC',
            ['organizer' => $organizer->toString(), 'type' => $subjectType, 'id' => $subjectId],
        );

        return array_map(self::view(...), $rows);
    }

    /**
     * @param array<string, string|null> $row
     */
    private static function view(array $row): AttentionItemView
    {
        /** @var array<string, scalar|null> $payload */
        $payload = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);
        $date = static fn(mixed $value): ?\DateTimeImmutable => \is_string($value) ? new \DateTimeImmutable($value) : null;

        return new AttentionItemView(
            AttentionItemId::fromString((string) $row['id']),
            AttentionType::from((string) $row['type']),
            AttentionState::from((string) $row['state']),
            (string) ($row['subject_type'] ?? ''),
            (string) ($row['subject_id'] ?? ''),
            \is_string($row['event_id'] ?? null) ? EventId::fromString($row['event_id']) : null,
            new \DateTimeImmutable((string) $row['created_at']),
            $payload,
            $date($row['acknowledged_at'] ?? null),
            $date($row['resolved_at'] ?? null),
            \is_string($row['resolution'] ?? null) ? $row['resolution'] : null,
            \is_string($row['resolution_note'] ?? null) ? $row['resolution_note'] : null,
        );
    }
}
