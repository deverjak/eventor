<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal;

use Doctrine\DBAL\Exception as DbalException;
use Eventor\Legal\Application\ArchiveRequirement\ArchiveRequirement;
use Eventor\Legal\Application\CreateDocument\CreateLegalDocument;
use Eventor\Legal\Application\DefineRequirement\DefineRequirement;
use Eventor\Legal\Application\DraftVersion\DraftDocumentVersion;
use Eventor\Legal\Application\Port\DocumentContentStore;
use Eventor\Legal\Application\PublishVersion\PublishDocumentVersion;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\Event\DocumentVersionPublished;
use Eventor\Legal\Contract\Exception\LegalDocumentNotFound;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Legal\Domain\Exception\DocumentVersionAlreadyPublished;
use Eventor\Legal\Domain\Exception\InvalidDocument;
use Eventor\Legal\Domain\Exception\InvalidRequirement;
use Eventor\Tests\Integration\Legal\Support\FakeExternalDocumentFetcher;
use Eventor\Tests\Integration\Legal\Support\Json;
use Eventor\Tests\Integration\Legal\Support\LegalTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Documents and their immutable, content-addressed versions (LEG-01 to LEG-03, ADR 012): the command handlers, their
 * audit entries and outbox messages, the stored bytes, and the immutability guard of the database.
 */
final class DocumentVersionsTest extends LegalTestCase
{
    public function test_a_document_is_created_audited_and_replaces_nothing_by_default(): void
    {
        $organizer = $this->organizer();

        /** @var array{documentId: string} $result */
        $result = $this->dispatch($organizer, new CreateLegalDocument(DocumentLevel::Organizer, $organizer->toString(), '  General terms '));

        $row = $this->dbRow('SELECT level, scope_id, title, current_version_id, replaces_document_id FROM legal.legal_document WHERE id = :id', ['id' => $result['documentId']]);
        self::assertSame(['level' => 'organizer', 'scope_id' => $organizer->toString(), 'title' => 'General terms', 'current_version_id' => null, 'replaces_document_id' => null], $row);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'legal.document_created' AND entity_id = :id", ['id' => $result['documentId']]));
    }

    public function test_an_organizer_document_must_have_the_organizer_as_scope(): void
    {
        $organizer = $this->organizer();

        $this->expectException(InvalidDocument::class);
        $this->dispatch($organizer, new CreateLegalDocument(DocumentLevel::Organizer, Uuid::v7()->toRfc4122(), 'Terms'));
    }

    public function test_a_document_can_only_replace_one_of_a_higher_level(): void
    {
        $organizer = $this->organizer();
        $event = $this->publishedDocument($organizer, DocumentLevel::Event);

        $this->expectException(InvalidDocument::class);
        $this->dispatch($organizer, new CreateLegalDocument(DocumentLevel::Organizer, $organizer->toString(), 'Terms', $event['documentId']));
    }

    public function test_a_text_version_is_stored_content_addressed_and_published_with_an_event(): void
    {
        $organizer = $this->organizer();

        $doc = $this->publishedDocument($organizer, text: "Line one\r\nLine two");

        $version = $this->dbRow('SELECT number, source_kind, mime, content_sha256, storage_key, requires_reconfirmation, published_at FROM legal.document_version WHERE id = :id', ['id' => $doc['versionId']]);
        $expectedHash = hash('sha256', "Line one\nLine two");
        self::assertSame(1, $version['number']);
        self::assertSame('text', $version['source_kind']);
        self::assertSame($expectedHash, $version['content_sha256']);
        self::assertSame(\sprintf('sha256/%s/%s.md', substr($expectedHash, 0, 2), $expectedHash), $version['storage_key']);
        self::assertNotNull($version['published_at']);
        self::assertSame("Line one\nLine two", $this->service(DocumentContentStore::class)->read(Json::string($version, 'storage_key')));
        self::assertSame($doc['versionId'], $this->dbString('SELECT current_version_id FROM legal.legal_document WHERE id = :id', ['id' => $doc['documentId']]));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'legal.version_published' AND entity_id = :id", ['id' => $doc['versionId']]));

        $this->drainQueues();
        $sent = $this->published('legal_events', DocumentVersionPublished::class);
        self::assertCount(1, $sent);
        self::assertSame($doc['versionId'], $sent[0]->versionId->toString());
        self::assertNull($sent[0]->previousVersionId);
        self::assertSame(DocumentLevel::Organizer, $sent[0]->level);
        self::assertFalse($sent[0]->requiresReconfirmation);
    }

    public function test_identical_content_is_stored_once_and_a_second_version_gets_the_next_number(): void
    {
        $organizer = $this->organizer();
        $first = $this->publishedDocument($organizer, text: 'Same text');
        $second = $this->publishedDocument($organizer, text: 'Same text', title: 'Another');
        $third = $this->publishVersion($organizer, $first['documentId'], 'Changed text');

        self::assertSame(
            $this->dbString('SELECT storage_key FROM legal.document_version WHERE id = :id', ['id' => $first['versionId']]),
            $this->dbString('SELECT storage_key FROM legal.document_version WHERE id = :id', ['id' => $second['versionId']]),
        );
        self::assertSame(2, $this->dbInt('SELECT number FROM legal.document_version WHERE id = :id', ['id' => $third]));
        self::assertSame($third, $this->dbString('SELECT current_version_id FROM legal.legal_document WHERE id = :id', ['id' => $first['documentId']]));
    }

    public function test_a_new_draft_replaces_the_unpublished_one_and_keeps_its_number(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        /** @var array{versionId: string, number: int} $one */
        $one = $this->dispatch($organizer, new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::TEXT, text: 'Draft one'));
        /** @var array{versionId: string, number: int} $two */
        $two = $this->dispatch($organizer, new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::TEXT, text: 'Draft two'));

        self::assertSame(2, $one['number']);
        self::assertSame(2, $two['number']);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM legal.document_version WHERE id = :id', ['id' => $one['versionId']]));
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM legal.document_version WHERE document_id = :id', ['id' => $doc['documentId']]));
    }

    public function test_the_reconfirmation_flag_of_the_first_version_is_ignored(): void
    {
        $organizer = $this->organizer();
        /** @var array{documentId: string} $document */
        $document = $this->dispatch($organizer, new CreateLegalDocument(DocumentLevel::Organizer, $organizer->toString(), 'Terms'));
        $this->publishVersion($organizer, $document['documentId'], 'v1', reconfirm: true);

        self::assertFalse($this->dbRow('SELECT requires_reconfirmation FROM legal.document_version WHERE document_id = :id', ['id' => $document['documentId']])['requires_reconfirmation']);
    }

    public function test_publishing_twice_is_a_conflict_and_a_published_row_cannot_be_changed(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        try {
            $this->dispatch($organizer, new PublishDocumentVersion($doc['documentId'], $doc['versionId']));
            self::fail('Publishing twice must fail.');
        } catch (DocumentVersionAlreadyPublished) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(DbalException::class);
        $this->connection()->executeStatement('UPDATE legal.document_version SET mime = :mime WHERE id = :id', ['mime' => 'text/plain', 'id' => $doc['versionId']]);
    }

    public function test_a_published_row_cannot_be_deleted(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        $this->expectException(DbalException::class);
        $this->connection()->executeStatement('DELETE FROM legal.document_version WHERE id = :id', ['id' => $doc['versionId']]);
    }

    public function test_a_pdf_is_validated_and_stored_as_uploaded(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF";

        /** @var array{versionId: string, sha256: string} $draft */
        $draft = $this->dispatch($organizer, new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::PDF, pdfBase64: base64_encode($pdf)));

        self::assertSame(hash('sha256', $pdf), $draft['sha256']);
        self::assertSame('application/pdf', $this->dbString('SELECT mime FROM legal.document_version WHERE id = :id', ['id' => $draft['versionId']]));
    }

    public function test_content_that_is_not_acceptable_is_refused(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        foreach ([
            new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::PDF, pdfBase64: base64_encode('not a pdf')),
            new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::PDF, pdfBase64: '!!!'),
            new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::TEXT, text: "  \n "),
            new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::TEXT),
            new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::EXTERNAL_LINK, url: 'https://unknown.example.test/terms'),
        ] as $command) {
            try {
                $this->dispatch($organizer, $command);
                self::fail('Expected the content to be refused.');
            } catch (InvalidDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_external_link_is_stored_as_an_immutable_snapshot_with_source_and_time(): void
    {
        $organizer = $this->organizer();
        self::mockTime('2026-11-01 12:00:00');
        FakeExternalDocumentFetcher::serve('https://club.example.test/terms', '<html><body>Terms of 1 November</body></html>');
        /** @var array{documentId: string} $document */
        $document = $this->dispatch($organizer, new CreateLegalDocument(DocumentLevel::Organizer, $organizer->toString(), 'Terms online'));

        /** @var array{versionId: string, sha256: string} $draft */
        $draft = $this->dispatch($organizer, new DraftDocumentVersion($document['documentId'], DraftDocumentVersion::EXTERNAL_LINK, url: 'https://club.example.test/terms'));
        FakeExternalDocumentFetcher::serve('https://club.example.test/terms', '<html><body>Terms changed later</body></html>');

        $row = $this->dbRow('SELECT source_kind, source_url, fetched_at, storage_key, mime FROM legal.document_version WHERE id = :id', ['id' => $draft['versionId']]);
        self::assertSame('external_snapshot', $row['source_kind']);
        self::assertSame('https://club.example.test/terms', $row['source_url']);
        self::assertStringStartsWith('2026-11-01 12:00:00', Json::string($row, 'fetched_at'));
        self::assertSame('<html><body>Terms of 1 November</body></html>', $this->service(DocumentContentStore::class)->read(Json::string($row, 'storage_key')));
    }

    public function test_a_document_of_another_organizer_is_not_found(): void
    {
        $alpha = $this->organizer('alpha');
        $beta = $this->organizer('beta');
        $doc = $this->publishedDocument($alpha);

        $this->expectException(LegalDocumentNotFound::class);
        $this->dispatch($beta, new DraftDocumentVersion($doc['documentId'], DraftDocumentVersion::TEXT, text: 'Hijack'));
    }

    public function test_a_requirement_is_defined_audited_and_archived(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        $id = $this->requirement($organizer, $doc['documentId'], RequirementType::Terms, confirmer: Confirmer::Orderer);

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'legal.requirement_defined' AND entity_id = :id", ['id' => $id->toString()]));
        $this->dispatch($organizer, new ArchiveRequirement($id->toString()));
        self::assertNotNull($this->dbRow('SELECT archived_at FROM legal.legal_requirement WHERE id = :id', ['id' => $id->toString()])['archived_at']);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'legal.requirement_archived' AND entity_id = :id", ['id' => $id->toString()]));
    }

    public function test_a_mandatory_or_irrevocable_voluntary_consent_is_refused(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        foreach ([
            new DefineRequirement($doc['documentId'], RequirementType::Consent, 'Newsletter', mandatory: true),
            new DefineRequirement($doc['documentId'], RequirementType::Likeness, 'Photos', revocable: false),
            new DefineRequirement($doc['documentId'], RequirementType::Consent, 'Newsletter', preChecked: true),
        ] as $command) {
            try {
                $this->dispatch($organizer, $command);
                self::fail('Expected the requirement to be refused.');
            } catch (InvalidRequirement|\Symfony\Component\Messenger\Exception\ValidationFailedException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM legal.legal_requirement'));
    }

    public function test_the_database_itself_rejects_a_mandatory_consent(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);

        $this->expectException(DbalException::class);
        $this->connection()->executeStatement(
            "INSERT INTO legal.legal_requirement (id, organizer_id, document_id, type, purpose, confirmer, revocable, mandatory, created_at)
             VALUES (:id, :org, :doc, 'consent', 'x', 'each_participant', TRUE, TRUE, now())",
            ['id' => Uuid::v7()->toRfc4122(), 'org' => $organizer->toString(), 'doc' => $doc['documentId']],
        );
    }
}
