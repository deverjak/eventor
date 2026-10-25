<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Audit: the append-only audit trail. The application role may INSERT and SELECT only (chapter 03, "Append-only a granty").
 */
final class Version20261025090200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: audit_entry (append-only for eventor_app)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE audit.audit_entry (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID DEFAULT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                actor_type VARCHAR(32) NOT NULL,
                actor_id VARCHAR(64) DEFAULT NULL,
                actor_label VARCHAR(128) DEFAULT NULL,
                entity_type VARCHAR(64) NOT NULL,
                entity_id VARCHAR(64) NOT NULL,
                action VARCHAR(96) NOT NULL,
                before JSONB DEFAULT NULL,
                after JSONB DEFAULT NULL,
                reason TEXT DEFAULT NULL,
                correlation_id VARCHAR(64) NOT NULL,
                occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT audit_entry_actor_type_check CHECK (actor_type IN ('USER', 'SYSTEM', 'PARTICIPANT_TOKEN', 'DEVICE', 'PAIRROT'))
            )
            SQL);
        $this->addSql('CREATE INDEX audit_entry_entity_idx ON audit.audit_entry (organizer_id, entity_type, entity_id, occurred_at)');

        $this->addSql('REVOKE UPDATE, DELETE, TRUNCATE ON audit.audit_entry FROM eventor_app');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit.audit_entry');
    }
}
