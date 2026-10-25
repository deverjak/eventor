<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Attention: deduplicate unresolved items per organizer. The same cause (e.g. a Pairrot outage) at two organizers
 * must open two items; platform-wide items (no organizer) deduplicate among themselves.
 */
final class Version20261026120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attention: unresolved-item deduplication per organizer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX attention.attention_item_dedupe_key_unresolved');
        $this->addSql("CREATE UNIQUE INDEX attention_item_dedupe_key_unresolved ON attention.attention_item ((coalesce(organizer_id::text, '')), dedupe_key) WHERE state IN ('open', 'acknowledged')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX attention.attention_item_dedupe_key_unresolved');
        // Several organizers may hold an unresolved item of one cause now: keep the newest before the global index.
        $this->addSql("DELETE FROM attention.attention_item a USING attention.attention_item b WHERE a.dedupe_key = b.dedupe_key AND a.state IN ('open', 'acknowledged') AND b.state IN ('open', 'acknowledged') AND (a.created_at, a.id) < (b.created_at, b.id)");
        $this->addSql("CREATE UNIQUE INDEX attention_item_dedupe_key_unresolved ON attention.attention_item (dedupe_key) WHERE state IN ('open', 'acknowledged')");
    }
}
