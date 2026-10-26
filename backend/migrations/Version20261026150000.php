<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Content (W4, POST-01..05, POST-07, PUB-06): collections of an event (an internal gallery or a link to an external
 * one), their photos in object storage, and post-event materials. Every item is a draft until published; `in_archive`
 * designates what the public archive of a finished event shows (never personal data).
 */
final class Version20261026150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Content: collection, photo, material';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE content.collection (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                event_id UUID NOT NULL,
                title VARCHAR(200) NOT NULL,
                visibility VARCHAR(20) NOT NULL CHECK (visibility IN ('public', 'link_only', 'participants')),
                external_url VARCHAR(2000) DEFAULT NULL,
                link_token VARCHAR(64) DEFAULT NULL,
                in_archive BOOLEAN NOT NULL DEFAULT FALSE,
                published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT content_collection_link_token CHECK ((visibility = 'link_only') = (link_token IS NOT NULL))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX content_collection_link_token_key ON content.collection (link_token) WHERE link_token IS NOT NULL');
        $this->addSql('CREATE INDEX content_collection_event_idx ON content.collection (organizer_id, event_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE content.photo (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                collection_id UUID NOT NULL REFERENCES content.collection (id) ON DELETE RESTRICT,
                storage_key VARCHAR(200) NOT NULL,
                thumbnail_key VARCHAR(200) DEFAULT NULL,
                content_type VARCHAR(40) NOT NULL,
                description VARCHAR(1000) DEFAULT NULL,
                author VARCHAR(200) DEFAULT NULL,
                event_part VARCHAR(200) DEFAULT NULL,
                position INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX content_photo_collection_idx ON content.photo (collection_id, position)');

        $this->addSql(<<<'SQL'
            CREATE TABLE content.material (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                event_id UUID NOT NULL,
                title VARCHAR(200) NOT NULL,
                url VARCHAR(2000) NOT NULL,
                type VARCHAR(20) NOT NULL CHECK (type IN ('results', 'video', 'document', 'partners', 'other')),
                position INT NOT NULL DEFAULT 0,
                in_archive BOOLEAN NOT NULL DEFAULT FALSE,
                published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX content_material_event_idx ON content.material (organizer_id, event_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content.material');
        $this->addSql('DROP TABLE content.photo');
        $this->addSql('DROP TABLE content.collection');
    }
}
