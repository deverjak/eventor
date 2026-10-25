<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalog: events, activities, variants, program, news and the versioned registration form (chapter 03, "Katalog,
 * formulář a ostatní moduly"). Capacity bucket ids are assigned here, the bucket rows are owned by Registration
 * (no foreign key: the table lives in another schema and is created when the event is published).
 */
final class Version20261025120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalog: event, activity, variant, program_item, news_post, form_version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE catalog.event (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                slug VARCHAR(100) NOT NULL,
                name VARCHAR(200) NOT NULL,
                short_description VARCHAR(500) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                cover_image_url VARCHAR(500) DEFAULT NULL,
                contact_name VARCHAR(200) DEFAULT NULL,
                contact_email VARCHAR(255) DEFAULT NULL,
                contact_phone VARCHAR(50) DEFAULT NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague',
                starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                ends_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                age_reference_date DATE DEFAULT NULL,
                visibility VARCHAR(16) NOT NULL DEFAULT 'public',
                publication_state VARCHAR(16) NOT NULL DEFAULT 'draft',
                registration_state VARCHAR(16) NOT NULL DEFAULT 'not_open',
                registration_opens_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                registration_closes_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                capacity_limit INT DEFAULT NULL,
                capacity_bucket_id UUID NOT NULL,
                payment_rules JSONB NOT NULL,
                venues JSONB NOT NULL DEFAULT '[]',
                price_list_version INT NOT NULL DEFAULT 1,
                cancellation_reason TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                version INT NOT NULL DEFAULT 1,
                CONSTRAINT event_organizer_slug_key UNIQUE (organizer_id, slug),
                CONSTRAINT event_publication_state_check CHECK (publication_state IN ('draft', 'published', 'completed', 'cancelled', 'archived')),
                CONSTRAINT event_registration_state_check CHECK (registration_state IN ('not_open', 'open', 'paused', 'closed')),
                CONSTRAINT event_visibility_check CHECK (visibility IN ('public', 'unlisted')),
                CONSTRAINT event_period_check CHECK (ends_at >= starts_at),
                CONSTRAINT event_capacity_limit_check CHECK (capacity_limit IS NULL OR capacity_limit >= 0)
            )
            SQL);
        $this->addSql('CREATE INDEX event_organizer_state_idx ON catalog.event (organizer_id, publication_state, starts_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog.activity (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                event_id UUID NOT NULL REFERENCES catalog.event (id) ON DELETE RESTRICT,
                name VARCHAR(200) NOT NULL,
                description TEXT DEFAULT NULL,
                location VARCHAR(200) DEFAULT NULL,
                starts_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                ends_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                implicit BOOLEAN NOT NULL DEFAULT FALSE,
                registration_state VARCHAR(16) NOT NULL DEFAULT 'not_open',
                registration_opens_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                registration_closes_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                min_age INT DEFAULT NULL,
                max_age INT DEFAULT NULL,
                minors_only BOOLEAN NOT NULL DEFAULT FALSE,
                capacity_limit INT DEFAULT NULL,
                capacity_bucket_id UUID NOT NULL,
                position INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                version INT NOT NULL DEFAULT 1,
                CONSTRAINT activity_registration_state_check CHECK (registration_state IN ('not_open', 'open', 'paused', 'closed')),
                CONSTRAINT activity_age_check CHECK (min_age IS NULL OR max_age IS NULL OR min_age <= max_age),
                CONSTRAINT activity_capacity_limit_check CHECK (capacity_limit IS NULL OR capacity_limit >= 0)
            )
            SQL);
        $this->addSql('CREATE INDEX activity_event_idx ON catalog.activity (organizer_id, event_id, position)');

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog.variant (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                event_id UUID NOT NULL REFERENCES catalog.event (id) ON DELETE RESTRICT,
                activity_id UUID NOT NULL REFERENCES catalog.activity (id) ON DELETE CASCADE,
                name VARCHAR(200) NOT NULL,
                price_minor BIGINT NOT NULL,
                price_currency CHAR(3) NOT NULL,
                capacity_limit INT DEFAULT NULL,
                capacity_bucket_id UUID NOT NULL,
                capacity_unit VARCHAR(8) NOT NULL DEFAULT 'seat',
                team_size INT DEFAULT NULL,
                min_age INT DEFAULT NULL,
                max_age INT DEFAULT NULL,
                position INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                version INT NOT NULL DEFAULT 1,
                CONSTRAINT variant_price_check CHECK (price_minor >= 0),
                CONSTRAINT variant_capacity_unit_check CHECK (capacity_unit IN ('seat', 'team')),
                CONSTRAINT variant_age_check CHECK (min_age IS NULL OR max_age IS NULL OR min_age <= max_age),
                CONSTRAINT variant_team_size_check CHECK (team_size IS NULL OR team_size >= 1),
                CONSTRAINT variant_capacity_limit_check CHECK (capacity_limit IS NULL OR capacity_limit >= 0)
            )
            SQL);
        $this->addSql('CREATE INDEX variant_activity_idx ON catalog.variant (organizer_id, activity_id, position)');
        $this->addSql('CREATE INDEX variant_event_idx ON catalog.variant (organizer_id, event_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog.program_item (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                event_id UUID NOT NULL REFERENCES catalog.event (id) ON DELETE CASCADE,
                activity_id UUID DEFAULT NULL REFERENCES catalog.activity (id) ON DELETE SET NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT DEFAULT NULL,
                location VARCHAR(200) DEFAULT NULL,
                responsible VARCHAR(200) DEFAULT NULL,
                starts_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                ends_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                visibility VARCHAR(16) NOT NULL DEFAULT 'public',
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT program_item_visibility_check CHECK (visibility IN ('public', 'participants')),
                CONSTRAINT program_item_period_check CHECK (ends_at IS NULL OR ends_at >= starts_at)
            )
            SQL);
        $this->addSql('CREATE INDEX program_item_event_idx ON catalog.program_item (organizer_id, event_id, starts_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog.news_post (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                event_id UUID NOT NULL REFERENCES catalog.event (id) ON DELETE RESTRICT,
                title VARCHAR(200) NOT NULL,
                body TEXT NOT NULL,
                priority INT NOT NULL DEFAULT 0,
                broadcast BOOLEAN NOT NULL DEFAULT FALSE,
                author_account_id UUID DEFAULT NULL REFERENCES identity.user_account (id) ON DELETE RESTRICT,
                published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX news_post_event_idx ON catalog.news_post (organizer_id, event_id, published_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog.form_version (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                event_id UUID NOT NULL REFERENCES catalog.event (id) ON DELETE RESTRICT,
                number INT NOT NULL,
                schema JSONB NOT NULL,
                schema_sha256 CHAR(64) DEFAULT NULL,
                published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_by UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT form_version_event_number_key UNIQUE (event_id, number),
                CONSTRAINT form_version_number_check CHECK (number >= 1),
                CONSTRAINT form_version_published_check CHECK ((published_at IS NULL) = (schema_sha256 IS NULL))
            )
            SQL);
        // One mutable draft per event; a published version is frozen by the domain (ADR 007).
        $this->addSql('CREATE UNIQUE INDEX form_version_one_draft ON catalog.form_version (event_id) WHERE published_at IS NULL');
        $this->addSql('CREATE INDEX form_version_event_idx ON catalog.form_version (organizer_id, event_id, number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog.form_version');
        $this->addSql('DROP TABLE catalog.news_post');
        $this->addSql('DROP TABLE catalog.program_item');
        $this->addSql('DROP TABLE catalog.variant');
        $this->addSql('DROP TABLE catalog.activity');
        $this->addSql('DROP TABLE catalog.event');
    }
}
