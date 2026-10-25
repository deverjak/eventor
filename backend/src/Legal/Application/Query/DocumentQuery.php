<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Legal\Application\Port\DocumentContentStore;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\Exception\LegalDocumentNotFound;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * DBAL read model of documents and versions for the admin API and the public content endpoints. Every method takes
 * the organizer explicitly (Doctrine filters do not apply to DBAL, ADR 005).
 */
final readonly class DocumentQuery
{
    private const string VERSION_COLUMNS = 'v.id, v.document_id, v.number, v.source_kind, v.mime, v.content_sha256 AS sha256, v.source_url, v.fetched_at,
        v.requires_reconfirmation, v.created_at, v.published_at, v.storage_key';

    public function __construct(
        private Connection $connection,
        private DocumentContentStore $store,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(OrganizerId $organizer, ?DocumentLevel $level, ?string $scopeId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.id, d.level, d.scope_id, d.title, d.replaces_document_id, d.created_at,
                    v.id AS current_version_id, v.number AS current_version_number, v.published_at AS current_published_at
               FROM legal.legal_document d
               LEFT JOIN legal.document_version v ON v.id = d.current_version_id
              WHERE d.organizer_id = :organizer AND (CAST(:level AS text) IS NULL OR d.level = :level) AND (CAST(:scope AS text) IS NULL OR d.scope_id = CAST(:scope AS uuid))
              ORDER BY d.created_at, d.id',
            ['organizer' => $organizer->toString(), 'level' => $level?->value, 'scope' => $scopeId],
        );

        return array_map($this->documentRow(...), $rows);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws LegalDocumentNotFound
     */
    public function get(OrganizerId $organizer, LegalDocumentId $id): array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT d.id, d.level, d.scope_id, d.title, d.replaces_document_id, d.created_at,
                    v.id AS current_version_id, v.number AS current_version_number, v.published_at AS current_published_at
               FROM legal.legal_document d
               LEFT JOIN legal.document_version v ON v.id = d.current_version_id
              WHERE d.organizer_id = :organizer AND d.id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );
        if (false === $row) {
            throw LegalDocumentNotFound::forId($id);
        }

        /** @var list<array<string, mixed>> $versions */
        $versions = $this->connection->fetchAllAssociative(
            'SELECT '.self::VERSION_COLUMNS.' FROM legal.document_version v WHERE v.organizer_id = :organizer AND v.document_id = :id ORDER BY v.number DESC',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );

        return $this->documentRow($row) + ['versions' => array_map($this->versionRow(...), $versions)];
    }

    /**
     * @throws LegalDocumentNotFound
     */
    public function levelOf(OrganizerId $organizer, LegalDocumentId $id): DocumentLevel
    {
        $level = $this->connection->fetchOne('SELECT level FROM legal.legal_document WHERE organizer_id = :organizer AND id = :id', ['organizer' => $organizer->toString(), 'id' => $id->toString()]);
        if (!\is_string($level)) {
            throw LegalDocumentNotFound::forId($id);
        }

        return DocumentLevel::from($level);
    }

    /**
     * One version of a document for the admin, drafts included, with its text when it is a text document.
     *
     * @return array<string, mixed>
     *
     * @throws LegalDocumentNotFound
     */
    public function version(OrganizerId $organizer, LegalDocumentId $document, DocumentVersionId $version): array
    {
        $row = $this->versionRowOf($organizer, $version);
        if (null === $row || $row['document_id'] !== $document->toString()) {
            throw LegalDocumentNotFound::forId($document);
        }

        return $this->withText($row);
    }

    /**
     * A published version, as the registration form shows it. Null when unknown, a draft or of another organizer.
     *
     * @return array<string, mixed>|null
     */
    public function publishedVersion(OrganizerId $organizer, DocumentVersionId $version): ?array
    {
        $row = $this->versionRowOf($organizer, $version);

        return null === $row || null === $row['published_at'] ? null : $this->withText($row);
    }

    /**
     * Metadata of the published version with this content hash (the same content may be shared by several versions).
     *
     * @return array<string, mixed>|null
     */
    public function publishedByHash(OrganizerId $organizer, string $sha256): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::VERSION_COLUMNS.', d.title
               FROM legal.document_version v JOIN legal.legal_document d ON d.id = v.document_id
              WHERE v.organizer_id = :organizer AND v.content_sha256 = :hash AND v.published_at IS NOT NULL
              ORDER BY v.published_at LIMIT 1',
            ['organizer' => $organizer->toString(), 'hash' => strtolower($sha256)],
        );

        return false === $row ? null : $row;
    }

    public function bytes(string $storageKey): string
    {
        return $this->store->read($storageKey);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function documentRow(array $row): array
    {
        $current = null === $row['current_version_id'] ? null : [
            'id' => $row['current_version_id'],
            'number' => Cells::int($row['current_version_number']),
            'publishedAt' => self::time($row['current_published_at']),
        ];

        return [
            'id' => $row['id'],
            'level' => $row['level'],
            'scopeId' => $row['scope_id'],
            'title' => $row['title'],
            'replacesDocumentId' => $row['replaces_document_id'],
            'currentVersion' => $current,
            'createdAt' => self::time($row['created_at']),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function versionRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'documentId' => $row['document_id'],
            'number' => Cells::int($row['number']),
            'state' => null === $row['published_at'] ? 'draft' : 'published',
            'sourceKind' => $row['source_kind'],
            'mime' => $row['mime'],
            'sha256' => $row['sha256'],
            'sourceUrl' => $row['source_url'],
            'fetchedAt' => self::time($row['fetched_at']),
            'requiresReconfirmation' => (bool) $row['requires_reconfirmation'],
            'createdAt' => self::time($row['created_at']),
            'publishedAt' => self::time($row['published_at']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function versionRowOf(OrganizerId $organizer, DocumentVersionId $version): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT '.self::VERSION_COLUMNS.', d.title
               FROM legal.document_version v JOIN legal.legal_document d ON d.id = v.document_id
              WHERE v.organizer_id = :organizer AND v.id = :id',
            ['organizer' => $organizer->toString(), 'id' => $version->toString()],
        );

        return false === $row ? null : $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withText(array $row): array
    {
        $version = $this->versionRow($row) + ['title' => $row['title']];
        $version['text'] = 'text' === $row['source_kind'] ? $this->store->read(Cells::string($row['storage_key'])) : null;

        return $version;
    }

    private static function time(mixed $value): ?string
    {
        return \is_string($value) ? (new \DateTimeImmutable($value))->format(\DATE_ATOM) : null;
    }
}
