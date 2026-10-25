<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Identity completion (W2): organizer profile and Pairrot state, organizer_policy, membership_event_scope,
 * invitation, device_token.
 */
final class Version20261025170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: organizer profile, organizer_policy, membership_event_scope, invitation, device_token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE identity.organizer
                ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL,
                ADD COLUMN contact_phone VARCHAR(50) DEFAULT NULL,
                ADD COLUMN legal_name VARCHAR(200) DEFAULT NULL,
                ADD COLUMN company_id VARCHAR(20) DEFAULT NULL,
                ADD COLUMN vat_id VARCHAR(20) DEFAULT NULL,
                ADD COLUMN billing_street VARCHAR(200) DEFAULT NULL,
                ADD COLUMN billing_city VARCHAR(100) DEFAULT NULL,
                ADD COLUMN billing_postal_code VARCHAR(20) DEFAULT NULL,
                ADD COLUMN billing_country VARCHAR(2) DEFAULT NULL,
                ADD COLUMN logo_url VARCHAR(500) DEFAULT NULL,
                ADD COLUMN cover_image_url VARCHAR(500) DEFAULT NULL,
                ADD COLUMN brand_color VARCHAR(7) DEFAULT NULL,
                ADD COLUMN pairrot_subtenant_id VARCHAR(100) DEFAULT NULL,
                ADD COLUMN pairrot_bank_connection_id VARCHAR(100) DEFAULT NULL,
                ADD COLUMN masked_iban VARCHAR(64) DEFAULT NULL,
                ADD COLUMN transfer_enabled BOOLEAN NOT NULL DEFAULT FALSE,
                ADD COLUMN auto_matching BOOLEAN NOT NULL DEFAULT TRUE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE identity.organizer_policy (
                organizer_id UUID NOT NULL PRIMARY KEY REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                check_in_may_record_cash BOOLEAN NOT NULL DEFAULT FALSE,
                event_manager_may_resolve_payments BOOLEAN NOT NULL DEFAULT FALSE,
                auto_restore_late_payment BOOLEAN NOT NULL DEFAULT FALSE,
                overpayment_handling VARCHAR(16) NOT NULL DEFAULT 'ask',
                default_due_days INT NOT NULL DEFAULT 14,
                default_hold_ttl_hours INT NOT NULL DEFAULT 48,
                CONSTRAINT organizer_policy_overpayment_check CHECK (overpayment_handling IN ('refund', 'donation', 'ask')),
                CONSTRAINT organizer_policy_due_days_check CHECK (default_due_days BETWEEN 1 AND 365),
                CONSTRAINT organizer_policy_hold_ttl_check CHECK (default_hold_ttl_hours BETWEEN 1 AND 720)
            )
            SQL);
        $this->addSql('INSERT INTO identity.organizer_policy (organizer_id) SELECT id FROM identity.organizer');

        $this->addSql(<<<'SQL'
            CREATE TABLE identity.membership_event_scope (
                membership_id UUID NOT NULL REFERENCES identity.membership (id) ON DELETE CASCADE,
                event_id UUID NOT NULL,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                PRIMARY KEY (membership_id, event_id)
            )
            SQL);
        $this->addSql('CREATE INDEX membership_event_scope_organizer_idx ON identity.membership_event_scope (organizer_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE identity.invitation (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                email VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL,
                event_ids JSONB NOT NULL DEFAULT '[]',
                token_sha256 CHAR(64) NOT NULL,
                invited_by UUID NOT NULL REFERENCES identity.user_account (id) ON DELETE RESTRICT,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                accepted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT invitation_role_check CHECK (role IN ('OWNER', 'EVENT_MANAGER', 'FINANCE', 'CHECK_IN')),
                CONSTRAINT invitation_email_lower CHECK (email = lower(email))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX invitation_token_key ON identity.invitation (token_sha256)');
        $this->addSql('CREATE INDEX invitation_organizer_idx ON identity.invitation (organizer_id, email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE identity.device_token (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                check_in_list_id UUID DEFAULT NULL,
                label VARCHAR(100) NOT NULL,
                secret_sha256 CHAR(64) NOT NULL,
                created_by UUID NOT NULL REFERENCES identity.user_account (id) ON DELETE RESTRICT,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX device_token_organizer_idx ON identity.device_token (organizer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity.device_token');
        $this->addSql('DROP TABLE identity.invitation');
        $this->addSql('DROP TABLE identity.membership_event_scope');
        $this->addSql('DROP TABLE identity.organizer_policy');
        $this->addSql(<<<'SQL'
            ALTER TABLE identity.organizer
                DROP COLUMN contact_email, DROP COLUMN contact_phone, DROP COLUMN legal_name, DROP COLUMN company_id,
                DROP COLUMN vat_id, DROP COLUMN billing_street, DROP COLUMN billing_city, DROP COLUMN billing_postal_code,
                DROP COLUMN billing_country, DROP COLUMN logo_url, DROP COLUMN cover_image_url, DROP COLUMN brand_color,
                DROP COLUMN pairrot_subtenant_id, DROP COLUMN pairrot_bank_connection_id, DROP COLUMN masked_iban,
                DROP COLUMN transfer_enabled, DROP COLUMN auto_matching
            SQL);
    }
}
