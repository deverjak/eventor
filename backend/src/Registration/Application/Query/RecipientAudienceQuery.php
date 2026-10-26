<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\AudienceCriteria;
use Eventor\Registration\Contract\AudienceMember;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\ParticipantRef;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\RecipientContact;
use Eventor\Registration\Contract\RecipientContext;
use Eventor\Registration\Contract\RecipientQuery;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\VariantId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Who a message goes to (COM-02..04): one member per orderer and activity (dedupe key `campaign:<id>:order:<id>`), the
 * re-check just before sending, and the contact and participants of an order for transactional messages. Contacts are
 * read at send time only.
 */
#[AsAlias(RecipientQuery::class)]
final readonly class RecipientAudienceQuery implements RecipientQuery
{
    public function __construct(private Connection $connection) {}

    public function resolveAudience(OrganizerId $organizer, AudienceCriteria $criteria): array
    {
        /** @var list<array{order_id: string, activity_id: string, ids: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT p.order_id, p.activity_id, string_agg(p.id::text, \',\' ORDER BY p.id) AS ids
               FROM registration.participation p
              WHERE p.organizer_id = :organizer AND p.event_id = :event'.$this->filter($criteria).'
              GROUP BY p.order_id, p.activity_id ORDER BY p.order_id, p.activity_id',
            $this->params($organizer, $criteria),
            $this->types($criteria),
        );

        return array_map(static fn(array $row): AudienceMember => new AudienceMember(
            OrderId::fromString($row['order_id']),
            ActivityId::fromString($row['activity_id']),
            array_map(static fn(string $id): ParticipationId => ParticipationId::fromString($id), explode(',', $row['ids'])),
        ), $rows);
    }

    public function stillInAudience(OrganizerId $organizer, AudienceCriteria $criteria, AudienceMember $member): bool
    {
        $found = $this->connection->fetchOne(
            'SELECT count(*) FROM registration.participation p
              WHERE p.organizer_id = :organizer AND p.event_id = :event AND p.order_id = :order AND p.activity_id = :activity'.$this->filter($criteria),
            $this->params($organizer, $criteria) + ['order' => $member->orderId->toString(), 'activity' => $member->activityId->toString()],
            $this->types($criteria),
        );

        return Cells::int($found) > 0;
    }

    public function forOrder(OrganizerId $organizer, OrderId $order): RecipientContext
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT number, event_id, orderer_first_name, orderer_last_name, orderer_email, orderer_locale FROM registration.orders WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $order->toString()],
        );
        if (false === $row) {
            throw OrderNotFound::forId($order);
        }

        /** @var list<array<string, mixed>> $people */
        $people = $this->connection->fetchAllAssociative(
            'SELECT p.id, p.activity_id, p.variant_id, p.state, pe.first_name, pe.last_name
               FROM registration.participation p JOIN registration.person pe ON pe.id = p.person_id
              WHERE p.order_id = :id ORDER BY p.created_at, p.id',
            ['id' => $order->toString()],
        );

        $name = trim(Cells::string($row['orderer_first_name'] ?? '').' '.Cells::string($row['orderer_last_name'] ?? ''));

        return new RecipientContext(
            $order,
            Cells::string($row['number']),
            EventId::fromString(Cells::string($row['event_id'])),
            new RecipientContact(Cells::string($row['orderer_email']), '' === $name ? Cells::string($row['orderer_email']) : $name, Cells::string($row['orderer_locale'])),
            array_map(static fn(array $p): ParticipantRef => new ParticipantRef(
                ParticipationId::fromString(Cells::string($p['id'])),
                trim(Cells::string($p['first_name'] ?? '').' '.Cells::string($p['last_name'] ?? '')),
                ActivityId::fromString(Cells::string($p['activity_id'])),
                VariantId::fromString(Cells::string($p['variant_id'])),
                ParticipationState::from(Cells::string($p['state'])),
            ), $people),
        );
    }

    private function filter(AudienceCriteria $criteria): string
    {
        $sql = ' AND p.state IN (:states)';
        if ([] !== $criteria->activityIds) {
            $sql .= ' AND p.activity_id IN (:activities)';
        }
        if ([] !== $criteria->variantIds) {
            $sql .= ' AND p.variant_id IN (:variants)';
        }

        return $sql;
    }

    /**
     * @return array<string, mixed>
     */
    private function params(OrganizerId $organizer, AudienceCriteria $criteria): array
    {
        $states = [] === $criteria->states
            ? [ParticipationState::Pending, ParticipationState::Confirmed, ParticipationState::RequiresAttention]
            : $criteria->states;
        $params = [
            'organizer' => $organizer->toString(),
            'event' => $criteria->eventId->toString(),
            'states' => array_map(static fn(ParticipationState $s): string => $s->value, $states),
        ];
        if ([] !== $criteria->activityIds) {
            $params['activities'] = array_map(static fn(ActivityId $id): string => $id->toString(), $criteria->activityIds);
        }
        if ([] !== $criteria->variantIds) {
            $params['variants'] = array_map(static fn(VariantId $id): string => $id->toString(), $criteria->variantIds);
        }

        return $params;
    }

    /**
     * @return array<string, ArrayParameterType>
     */
    private function types(AudienceCriteria $criteria): array
    {
        $types = ['states' => ArrayParameterType::STRING];
        if ([] !== $criteria->activityIds) {
            $types['activities'] = ArrayParameterType::STRING;
        }
        if ([] !== $criteria->variantIds) {
            $types['variants'] = ArrayParameterType::STRING;
        }

        return $types;
    }
}
