<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Registration: the capacity core (ADR 002). One row per capacity level with the held / confirmed counters that only
 * the conditional UPDATEs of the allocator move, plus the claims that record exactly what each participation occupies.
 */
final class Version20261025141733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registration: capacity_bucket, capacity_claim';
    }

    public function up(Schema $schema): void
    {
        // organizer_id and event_id are plain columns (no foreign keys): Identity and Catalog own those tables and
        // their migrations are independent. event_id is an addition to the book's table: it makes "all buckets of
        // an event" an indexed lookup (CapacityQuery::forEvent, AvailabilityReader, cache invalidation).
        $this->addSql(<<<'SQL'
            CREATE TABLE registration.capacity_bucket (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                event_id UUID NOT NULL,
                scope_type VARCHAR(16) NOT NULL,
                scope_id UUID NOT NULL,
                unit VARCHAR(8) NOT NULL DEFAULT 'seat',
                "limit" INTEGER DEFAULT NULL,
                held INTEGER NOT NULL DEFAULT 0,
                confirmed INTEGER NOT NULL DEFAULT 0,
                overbooked_allowed BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT capacity_bucket_scope_check CHECK (scope_type IN ('event', 'activity', 'variant', 'wave')),
                CONSTRAINT capacity_bucket_unit_check CHECK (unit IN ('seat', 'team')),
                CONSTRAINT capacity_bucket_limit_nonnegative CHECK ("limit" IS NULL OR "limit" >= 0),
                CONSTRAINT capacity_bucket_counters_nonnegative CHECK (held >= 0 AND confirmed >= 0),
                CONSTRAINT capacity_bucket_within_limit CHECK ("limit" IS NULL OR held + confirmed <= "limit" OR overbooked_allowed)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX capacity_bucket_scope_key ON registration.capacity_bucket (scope_type, scope_id)');
        $this->addSql('CREATE INDEX capacity_bucket_event_idx ON registration.capacity_bucket (organizer_id, event_id)');

        // participation_id has no foreign key yet: the participation table arrives with the order flow and adds it then.
        $this->addSql(<<<'SQL'
            CREATE TABLE registration.capacity_claim (
                participation_id UUID NOT NULL,
                bucket_id UUID NOT NULL REFERENCES registration.capacity_bucket (id) ON DELETE RESTRICT,
                seats INTEGER NOT NULL,
                status VARCHAR(12) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (participation_id, bucket_id),
                CONSTRAINT capacity_claim_seats_positive CHECK (seats > 0),
                CONSTRAINT capacity_claim_status_check CHECK (status IN ('held', 'confirmed', 'released'))
            )
            SQL);
        $this->addSql('CREATE INDEX capacity_claim_bucket_idx ON registration.capacity_claim (bucket_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE registration.capacity_claim');
        $this->addSql('DROP TABLE registration.capacity_bucket');
    }
}
