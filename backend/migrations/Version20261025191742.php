<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Legal: documents, immutable content-addressed versions, requirements and the append-only confirmation ledger
 * (ADR 012). Published versions are protected by a trigger; `confirmation` has UPDATE/DELETE/TRUNCATE revoked.
 */
final class Version20261025191742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Legal: legal_document, document_version, legal_requirement, confirmation (append-only)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE legal.legal_document (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                level VARCHAR(16) NOT NULL,
                scope_id UUID NOT NULL,
                title VARCHAR(200) NOT NULL,
                replaces_document_id UUID DEFAULT NULL REFERENCES legal.legal_document (id) ON DELETE RESTRICT,
                current_version_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT legal_document_level_check CHECK (level IN ('organizer', 'event', 'activity'))
            )
            SQL);
        $this->addSql('CREATE INDEX legal_document_scope_idx ON legal.legal_document (organizer_id, level, scope_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE legal.document_version (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                document_id UUID NOT NULL REFERENCES legal.legal_document (id) ON DELETE RESTRICT,
                number INTEGER NOT NULL,
                source_kind VARCHAR(24) NOT NULL,
                mime VARCHAR(100) NOT NULL,
                source_url VARCHAR(2000) DEFAULT NULL,
                fetched_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                content_sha256 CHAR(64) NOT NULL,
                storage_key VARCHAR(255) NOT NULL,
                requires_reconfirmation BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                published_by VARCHAR(64) DEFAULT NULL,
                CONSTRAINT document_version_source_kind_check CHECK (source_kind IN ('text', 'pdf', 'external_snapshot')),
                CONSTRAINT document_version_external_check CHECK (source_kind <> 'external_snapshot' OR (source_url IS NOT NULL AND fetched_at IS NOT NULL))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX document_version_number_key ON legal.document_version (document_id, number)');
        $this->addSql('CREATE INDEX document_version_hash_idx ON legal.document_version (organizer_id, content_sha256)');

        // ADR 012: a published version never changes and is never deleted; a draft (published_at IS NULL) may be replaced.
        $this->addSql(<<<'SQL'
            CREATE FUNCTION legal.document_version_immutable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.published_at IS NOT NULL THEN
                    RAISE EXCEPTION 'A published document version is immutable (%).', OLD.id USING ERRCODE = 'integrity_constraint_violation';
                END IF;
                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->addSql('CREATE TRIGGER document_version_immutable BEFORE UPDATE OR DELETE ON legal.document_version FOR EACH ROW EXECUTE FUNCTION legal.document_version_immutable()');

        $this->addSql(<<<'SQL'
            CREATE TABLE legal.legal_requirement (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                document_id UUID NOT NULL REFERENCES legal.legal_document (id) ON DELETE RESTRICT,
                type VARCHAR(24) NOT NULL,
                purpose VARCHAR(500) NOT NULL,
                scope_text TEXT DEFAULT NULL,
                validity VARCHAR(200) DEFAULT NULL,
                confirmer VARCHAR(24) NOT NULL,
                revocable BOOLEAN NOT NULL,
                mandatory BOOLEAN NOT NULL,
                activity_id UUID DEFAULT NULL,
                variant_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                archived_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT legal_requirement_type_check CHECK (type IN ('terms', 'privacy_notice', 'health_declaration', 'consent', 'likeness')),
                CONSTRAINT legal_requirement_confirmer_check CHECK (confirmer IN ('orderer', 'each_participant', 'guardian')),
                CONSTRAINT legal_requirement_voluntary_check CHECK (NOT (type IN ('consent', 'likeness') AND mandatory)),
                CONSTRAINT legal_requirement_voluntary_revocable_check CHECK (NOT (type IN ('consent', 'likeness') AND NOT revocable))
            )
            SQL);
        $this->addSql('CREATE INDEX legal_requirement_document_idx ON legal.legal_requirement (organizer_id, document_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE legal.confirmation (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                requirement_id UUID NOT NULL REFERENCES legal.legal_requirement (id) ON DELETE RESTRICT,
                document_version_id UUID NOT NULL REFERENCES legal.document_version (id) ON DELETE RESTRICT,
                person_id UUID NOT NULL,
                participation_id UUID DEFAULT NULL,
                order_id UUID DEFAULT NULL,
                decided_by_person_id UUID DEFAULT NULL,
                decision VARCHAR(16) NOT NULL,
                channel VARCHAR(16) NOT NULL,
                note TEXT DEFAULT NULL,
                decided_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                supersedes_id UUID DEFAULT NULL REFERENCES legal.confirmation (id) ON DELETE RESTRICT,
                CONSTRAINT confirmation_target_check CHECK (participation_id IS NOT NULL OR order_id IS NOT NULL),
                CONSTRAINT confirmation_decision_check CHECK (decision IN ('requested', 'granted', 'declined', 'withdrawn')),
                CONSTRAINT confirmation_channel_check CHECK (channel IN ('web', 'staff')),
                CONSTRAINT confirmation_staff_note_check CHECK (channel <> 'staff' OR (note IS NOT NULL AND btrim(note) <> ''))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX confirmation_supersedes_key ON legal.confirmation (supersedes_id) WHERE supersedes_id IS NOT NULL');
        $this->addSql('CREATE INDEX confirmation_chain_idx ON legal.confirmation (organizer_id, requirement_id, person_id, (COALESCE(participation_id, order_id)))');
        $this->addSql('CREATE INDEX confirmation_participation_idx ON legal.confirmation (organizer_id, participation_id) WHERE participation_id IS NOT NULL');
        $this->addSql('CREATE INDEX confirmation_order_idx ON legal.confirmation (organizer_id, order_id) WHERE order_id IS NOT NULL');
        $this->addSql('CREATE INDEX confirmation_version_idx ON legal.confirmation (document_version_id)');
        $this->addSql('REVOKE UPDATE, DELETE, TRUNCATE ON legal.confirmation FROM eventor_app');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE legal.confirmation');
        $this->addSql('DROP TABLE legal.legal_requirement');
        $this->addSql('DROP TRIGGER document_version_immutable ON legal.document_version');
        $this->addSql('DROP FUNCTION legal.document_version_immutable()');
        $this->addSql('DROP TABLE legal.document_version');
        $this->addSql('DROP TABLE legal.legal_document');
    }
}
