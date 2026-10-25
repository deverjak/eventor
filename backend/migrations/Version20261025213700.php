<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Attention: workflow states open / acknowledged / resolved / dismissed (lower case), event scope, resolution
 * columns, and deduplication per unresolved cause (a resolved cause may open a new item later).
 */
final class Version20261025213700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attention: attention_item workflow states, event_id, resolution columns, dedupe only among unresolved items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attention.attention_item DROP CONSTRAINT attention_item_state_check');
        $this->addSql("UPDATE attention.attention_item SET state = CASE state WHEN 'ASSIGNED' THEN 'acknowledged' ELSE lower(state) END");
        $this->addSql("ALTER TABLE attention.attention_item ADD CONSTRAINT attention_item_state_check CHECK (state IN ('open', 'acknowledged', 'resolved', 'dismissed'))");

        $this->addSql('ALTER TABLE attention.attention_item ADD COLUMN event_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE attention.attention_item ADD COLUMN acknowledged_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE attention.attention_item ADD COLUMN acknowledged_by VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE attention.attention_item ADD COLUMN resolved_by VARCHAR(128) DEFAULT NULL');
        $this->addSql('ALTER TABLE attention.attention_item ADD COLUMN resolution VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE attention.attention_item ADD COLUMN resolution_note TEXT DEFAULT NULL');

        $this->addSql('DROP INDEX attention.attention_item_dedupe_key_key');
        $this->addSql("CREATE UNIQUE INDEX attention_item_dedupe_key_unresolved ON attention.attention_item (dedupe_key) WHERE state IN ('open', 'acknowledged')");
        $this->addSql('CREATE INDEX attention_item_subject_idx ON attention.attention_item (organizer_id, subject_type, subject_id)');
        $this->addSql('CREATE INDEX attention_item_event_idx ON attention.attention_item (organizer_id, event_id) WHERE event_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX attention.attention_item_event_idx');
        $this->addSql('DROP INDEX attention.attention_item_subject_idx');
        $this->addSql('DROP INDEX attention.attention_item_dedupe_key_unresolved');
        // Resolved duplicates of one cause may exist now: keep the newest per dedupe key before restoring uniqueness.
        $this->addSql('DELETE FROM attention.attention_item a USING attention.attention_item b WHERE a.dedupe_key = b.dedupe_key AND (a.created_at, a.id) < (b.created_at, b.id)');
        $this->addSql('CREATE UNIQUE INDEX attention_item_dedupe_key_key ON attention.attention_item (dedupe_key)');
        foreach (['resolution_note', 'resolution', 'resolved_by', 'acknowledged_by', 'acknowledged_at', 'event_id'] as $column) {
            $this->addSql("ALTER TABLE attention.attention_item DROP COLUMN $column");
        }
        $this->addSql('ALTER TABLE attention.attention_item DROP CONSTRAINT attention_item_state_check');
        $this->addSql("UPDATE attention.attention_item SET state = CASE state WHEN 'acknowledged' THEN 'ASSIGNED' WHEN 'dismissed' THEN 'RESOLVED' ELSE upper(state) END");
        $this->addSql("ALTER TABLE attention.attention_item ADD CONSTRAINT attention_item_state_check CHECK (state IN ('OPEN', 'ASSIGNED', 'RESOLVED'))");
    }
}
