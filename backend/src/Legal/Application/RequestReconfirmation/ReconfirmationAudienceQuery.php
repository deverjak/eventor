<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\RequestReconfirmation;

use Doctrine\DBAL\Connection;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Who holds a granted confirmation of a requirement on a version other than the new one: the latest row of the chain
 * decides, so a withdrawn or declined decision is not asked again.
 */
final readonly class ReconfirmationAudienceQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return array{participations: list<ParticipationId>, orders: list<OrderId>}
     */
    public function forRequirement(OrganizerId $organizer, LegalRequirementId $requirement, DocumentVersionId $newVersion): array
    {
        /** @var list<array{participation_id: ?string, order_id: ?string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT c.participation_id, c.order_id
               FROM legal.confirmation c
              WHERE c.organizer_id = :organizer AND c.requirement_id = :requirement AND c.decision = 'granted'
                AND c.document_version_id <> :version
                AND NOT EXISTS (SELECT 1 FROM legal.confirmation n WHERE n.supersedes_id = c.id)",
            ['organizer' => $organizer->toString(), 'requirement' => $requirement->toString(), 'version' => $newVersion->toString()],
        );

        $participations = [];
        $orders = [];
        foreach ($rows as $row) {
            if (null !== $row['participation_id']) {
                $participations[$row['participation_id']] = ParticipationId::fromString($row['participation_id']);
            } elseif (null !== $row['order_id']) {
                $orders[$row['order_id']] = OrderId::fromString($row['order_id']);
            }
        }

        return ['participations' => array_values($participations), 'orders' => array_values($orders)];
    }
}
