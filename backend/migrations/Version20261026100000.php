<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Registration: the order aggregate (chapter 03, "Registrace a kapacity"). Orders, people, participations (the only
 * bearer of capacity), versioned answer sets, the self-service token and the per-organizer order-number counter. The
 * foreign key from `capacity_claim` to `participation` that the capacity core postponed is added here; it is
 * DEFERRABLE INITIALLY DEFERRED because `PlaceOrder` claims the seats (SQL) before the ORM inserts the participation
 * rows at flush, all in one transaction.
 *
 * Deviation from the book: the table is `orders`, not `order`, because `order` is a reserved word and Doctrine's
 * quoted table names break the mapping check and every raw query.
 */
final class Version20261026100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registration: orders, person, participation, answer_set, self_service_token, order_counter; capacity_claim -> participation FK';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE registration.orders (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                event_id UUID NOT NULL,
                number VARCHAR(32) NOT NULL,
                orderer_first_name VARCHAR(100) DEFAULT NULL,
                orderer_last_name VARCHAR(100) DEFAULT NULL,
                orderer_email VARCHAR(255) NOT NULL,
                orderer_phone VARCHAR(50) DEFAULT NULL,
                orderer_locale VARCHAR(8) NOT NULL DEFAULT 'cs',
                payment_method VARCHAR(16) NOT NULL,
                amount_due_minor BIGINT NOT NULL,
                amount_due_currency CHAR(3) NOT NULL,
                origin VARCHAR(12) NOT NULL DEFAULT 'public',
                form_version_id UUID NOT NULL,
                placed_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('bank_transfer', 'cash', 'free')),
                CONSTRAINT orders_origin_check CHECK (origin IN ('public', 'organizer')),
                CONSTRAINT orders_amount_due_nonnegative CHECK (amount_due_minor >= 0)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX orders_number_key ON registration.orders (organizer_id, number)');
        $this->addSql('CREATE INDEX orders_event_idx ON registration.orders (organizer_id, event_id)');
        $this->addSql('CREATE INDEX orders_orderer_email_idx ON registration.orders (organizer_id, lower(orderer_email))');

        // Order numbers `EV-2026-000123`: a counter per organizer and year, advanced by one atomic UPSERT.
        $this->addSql(<<<'SQL'
            CREATE TABLE registration.order_counter (
                organizer_id UUID NOT NULL,
                year INTEGER NOT NULL,
                last_value INTEGER NOT NULL,
                PRIMARY KEY (organizer_id, year)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE registration.person (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                order_id UUID NOT NULL REFERENCES registration.orders (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                first_name VARCHAR(100) DEFAULT NULL,
                last_name VARCHAR(100) DEFAULT NULL,
                birth_date DATE DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                guardian_person_id UUID DEFAULT NULL REFERENCES registration.person (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                anonymized_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT person_guardian_not_self CHECK (guardian_person_id IS NULL OR guardian_person_id <> id)
            )
            SQL);
        $this->addSql('CREATE INDEX person_order_idx ON registration.person (order_id)');
        $this->addSql('CREATE INDEX person_name_idx ON registration.person (organizer_id, lower(last_name), lower(first_name))');

        $this->addSql(<<<'SQL'
            CREATE TABLE registration.participation (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                order_id UUID NOT NULL REFERENCES registration.orders (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                person_id UUID NOT NULL REFERENCES registration.person (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                event_id UUID NOT NULL,
                activity_id UUID NOT NULL,
                variant_id UUID NOT NULL,
                wave_id UUID DEFAULT NULL,
                state VARCHAR(20) NOT NULL,
                legal_state VARCHAR(30) NOT NULL DEFAULT 'complete',
                answers_state VARCHAR(12) NOT NULL DEFAULT 'complete',
                hold_expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                registration_code VARCHAR(16) NOT NULL,
                qr_version INTEGER NOT NULL DEFAULT 1,
                category_code VARCHAR(32) DEFAULT NULL,
                price_minor BIGINT NOT NULL,
                price_currency CHAR(3) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                cancel_reason VARCHAR(500) DEFAULT NULL,
                cancelled_by VARCHAR(12) DEFAULT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                CONSTRAINT participation_state_check CHECK (state IN ('pending', 'confirmed', 'expired', 'requires_attention', 'cancelled', 'transferred')),
                CONSTRAINT participation_legal_state_check CHECK (legal_state IN ('complete', 'incomplete', 'reconfirmation_required')),
                CONSTRAINT participation_answers_state_check CHECK (answers_state IN ('complete', 'incomplete')),
                CONSTRAINT participation_hold_iff_pending CHECK ((state = 'pending') = (hold_expires_at IS NOT NULL)),
                CONSTRAINT participation_price_nonnegative CHECK (price_minor >= 0)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX participation_code_key ON registration.participation (organizer_id, registration_code)');
        $this->addSql('CREATE INDEX participation_order_idx ON registration.participation (order_id)');
        $this->addSql('CREATE INDEX participation_person_idx ON registration.participation (person_id)');
        $this->addSql('CREATE INDEX participation_event_state_idx ON registration.participation (organizer_id, event_id, state)');
        $this->addSql("CREATE INDEX participation_hold_idx ON registration.participation (hold_expires_at) WHERE state = 'pending'");

        $this->addSql(<<<'SQL'
            CREATE TABLE registration.answer_set (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                order_id UUID NOT NULL REFERENCES registration.orders (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                scope VARCHAR(16) NOT NULL,
                subject_id UUID NOT NULL,
                form_version_id UUID NOT NULL,
                answers JSONB NOT NULL,
                superseded_by_id UUID DEFAULT NULL REFERENCES registration.answer_set (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT answer_set_scope_check CHECK (scope IN ('order', 'person', 'participation', 'group'))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX answer_set_current_key ON registration.answer_set (scope, subject_id) WHERE superseded_by_id IS NULL');
        $this->addSql('CREATE INDEX answer_set_order_idx ON registration.answer_set (order_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE registration.self_service_token (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL,
                order_id UUID NOT NULL REFERENCES registration.orders (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED,
                secret_sha256 CHAR(64) NOT NULL,
                scope VARCHAR(64) NOT NULL DEFAULT 'order',
                issued_reason VARCHAR(32) NOT NULL,
                issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                last_used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX self_service_token_order_idx ON registration.self_service_token (order_id)');

        // The capacity core created the claims first; now the participation table exists. Deferred: the claim rows are
        // written by the allocator (SQL) before the ORM flush inserts the participation, inside the same transaction.
        $this->addSql('ALTER TABLE registration.capacity_claim ADD CONSTRAINT capacity_claim_participation_fk FOREIGN KEY (participation_id) REFERENCES registration.participation (id) ON DELETE RESTRICT DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registration.capacity_claim DROP CONSTRAINT capacity_claim_participation_fk');
        $this->addSql('DROP TABLE registration.self_service_token');
        $this->addSql('DROP TABLE registration.answer_set');
        $this->addSql('DROP TABLE registration.participation');
        $this->addSql('DROP TABLE registration.person');
        $this->addSql('DROP TABLE registration.order_counter');
        $this->addSql('DROP TABLE registration.orders');
    }
}
