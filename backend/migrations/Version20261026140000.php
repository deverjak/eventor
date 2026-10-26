<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CheckIn: check-in lists (one per activity by default) and check-ins (chapter 03, "Odbavení").
 *
 * `check_in` is partially append-only: the application role may INSERT and SELECT, and UPDATE only the three columns of
 * a void (CHK-07). Everything else about an arrival is immutable; a correction voids the old row and inserts a new one
 * (`corrects_id`). The partial unique index is the invariant of CHK-06 / S10: at most one valid check-in per
 * participation, whatever races at the desk.
 */
final class Version20261026140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CheckIn: check_in_list, check_in (one valid check-in per participation, void-only updates)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE checkin.check_in_list (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                event_id UUID NOT NULL,
                activity_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                opens_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX check_in_list_activity_key ON checkin.check_in_list (organizer_id, activity_id)');
        $this->addSql('CREATE INDEX check_in_list_event_idx ON checkin.check_in_list (organizer_id, event_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE checkin.check_in (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                participation_id UUID NOT NULL,
                list_id UUID NOT NULL REFERENCES checkin.check_in_list (id) ON DELETE RESTRICT,
                station_id UUID DEFAULT NULL,
                checked_in_by VARCHAR(64) DEFAULT NULL,
                device_label VARCHAR(100) DEFAULT NULL,
                checked_in_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                cash_minor BIGINT DEFAULT NULL,
                ledger_entry_id UUID DEFAULT NULL,
                corrects_id UUID DEFAULT NULL REFERENCES checkin.check_in (id) ON DELETE RESTRICT,
                voided_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                voided_by VARCHAR(64) DEFAULT NULL,
                void_reason VARCHAR(500) DEFAULT NULL,
                CONSTRAINT check_in_cash_positive CHECK (cash_minor IS NULL OR cash_minor > 0),
                CONSTRAINT check_in_void_complete CHECK (
                    (voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL)
                    OR (voided_at IS NOT NULL AND voided_by IS NOT NULL AND void_reason IS NOT NULL)
                )
            )
            SQL);
        // CHK-06 / S10: exactly one valid check-in per participation, enforced by the database.
        $this->addSql('CREATE UNIQUE INDEX check_in_valid_participation_key ON checkin.check_in (participation_id) WHERE voided_at IS NULL');
        $this->addSql('CREATE INDEX check_in_list_valid_idx ON checkin.check_in (organizer_id, list_id) WHERE voided_at IS NULL');
        $this->addSql('CREATE INDEX check_in_participation_idx ON checkin.check_in (organizer_id, participation_id)');

        $this->addSql('REVOKE UPDATE, DELETE, TRUNCATE ON checkin.check_in FROM eventor_app');
        $this->addSql('GRANT UPDATE (voided_at, voided_by, void_reason) ON checkin.check_in TO eventor_app');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE checkin.check_in');
        $this->addSql('DROP TABLE checkin.check_in_list');
    }
}
