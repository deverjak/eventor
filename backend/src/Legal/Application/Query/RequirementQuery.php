<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Legal\Domain\Exception\RequirementNotFound;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of requirements for the admin API.
 */
final readonly class RequirementQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(OrganizerId $organizer, ?LegalDocumentId $document, bool $includeArchived = false): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.id, r.document_id, d.title AS document_title, d.level, d.scope_id, r.type, r.purpose, r.scope_text, r.validity,
                    r.confirmer, r.revocable, r.mandatory, r.activity_id, r.variant_id, r.created_at, r.archived_at
               FROM legal.legal_requirement r JOIN legal.legal_document d ON d.id = r.document_id
              WHERE r.organizer_id = :organizer AND (CAST(:document AS text) IS NULL OR r.document_id = CAST(:document AS uuid))
                AND (:archived OR r.archived_at IS NULL)
              ORDER BY d.created_at, r.created_at, r.id',
            ['organizer' => $organizer->toString(), 'document' => $document?->toString(), 'archived' => $includeArchived],
            ['archived' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return array_map(static fn(array $row): array => [
            'id' => $row['id'],
            'documentId' => $row['document_id'],
            'documentTitle' => $row['document_title'],
            'level' => $row['level'],
            'scopeId' => $row['scope_id'],
            'type' => $row['type'],
            'purpose' => $row['purpose'],
            'scopeText' => $row['scope_text'],
            'validity' => $row['validity'],
            'confirmer' => $row['confirmer'],
            'revocable' => (bool) $row['revocable'],
            'mandatory' => (bool) $row['mandatory'],
            'activityId' => $row['activity_id'],
            'variantId' => $row['variant_id'],
            'archived' => null !== $row['archived_at'],
            'createdAt' => (new \DateTimeImmutable(Cells::string($row['created_at'])))->format(\DATE_ATOM),
        ], $rows);
    }

    /**
     * @throws RequirementNotFound
     */
    public function documentOf(OrganizerId $organizer, string $requirementId): string
    {
        $document = $this->connection->fetchOne('SELECT document_id FROM legal.legal_requirement WHERE organizer_id = :organizer AND id = :id', ['organizer' => $organizer->toString(), 'id' => $requirementId]);

        return \is_string($document) ? $document : throw RequirementNotFound::forId(LegalRequirementId::fromString($requirementId));
    }
}
