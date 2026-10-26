<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Communication (W3): message templates (COM-08), campaigns (COM-03/04), the delivery ledger with its idempotency key
 * (COM-06), the suppression list, and per-organizer reminder settings (PAY-09). Ids of other modules (`event_id`,
 * `order_id`, `activity_id`) are plain uuids: those tables belong to modules that are built in parallel.
 */
final class Version20261025230100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Communication: message_template, campaign, delivery, suppression, reminder_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE communication.message_template (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                template_key VARCHAR(64) NOT NULL,
                locale VARCHAR(5) NOT NULL,
                subject VARCHAR(300) NOT NULL,
                body TEXT NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT message_template_locale_check CHECK (locale IN ('cs', 'en')),
                CONSTRAINT message_template_unique UNIQUE (organizer_id, template_key, locale)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE communication.campaign (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                event_id UUID NOT NULL,
                name VARCHAR(200) NOT NULL,
                purpose VARCHAR(16) NOT NULL,
                template_key VARCHAR(64) DEFAULT NULL,
                locale VARCHAR(5) NOT NULL,
                subject VARCHAR(300) NOT NULL,
                body TEXT NOT NULL,
                audience JSONB NOT NULL,
                proposal_key VARCHAR(255) DEFAULT NULL,
                ref_id VARCHAR(64) DEFAULT NULL,
                state VARCHAR(16) NOT NULL,
                scheduled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                completed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT campaign_purpose_check CHECK (purpose IN ('organizational', 'marketing')),
                CONSTRAINT campaign_state_check CHECK (state IN ('draft', 'scheduled', 'sending', 'sent', 'cancelled')),
                CONSTRAINT campaign_locale_check CHECK (locale IN ('cs', 'en')),
                CONSTRAINT campaign_proposal_key_unique UNIQUE (proposal_key)
            )
            SQL);
        $this->addSql('CREATE INDEX campaign_organizer_event_idx ON communication.campaign (organizer_id, event_id, created_at DESC)');
        $this->addSql("CREATE INDEX campaign_due_idx ON communication.campaign (scheduled_at) WHERE state = 'scheduled'");

        $this->addSql(<<<'SQL'
            CREATE TABLE communication.delivery (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                campaign_id UUID DEFAULT NULL REFERENCES communication.campaign (id) ON DELETE RESTRICT,
                trigger_key VARCHAR(64) NOT NULL,
                template_key VARCHAR(64) NOT NULL,
                purpose VARCHAR(16) NOT NULL,
                event_id UUID DEFAULT NULL,
                order_id UUID DEFAULT NULL,
                activity_id UUID DEFAULT NULL,
                ref_id VARCHAR(64) DEFAULT NULL,
                params JSONB NOT NULL DEFAULT '{}',
                idempotency_key VARCHAR(255) NOT NULL,
                state VARCHAR(16) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                last_error VARCHAR(300) DEFAULT NULL,
                recipient_hint VARCHAR(255) DEFAULT NULL,
                provider_message_id VARCHAR(255) DEFAULT NULL,
                send_after TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                dispatched_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT delivery_idempotency_key_unique UNIQUE (idempotency_key),
                CONSTRAINT delivery_purpose_check CHECK (purpose IN ('organizational', 'marketing')),
                CONSTRAINT delivery_state_check CHECK (state IN ('scheduled', 'sent', 'delivered', 'skipped', 'failed')),
                CONSTRAINT delivery_attempts_check CHECK (attempts >= 0)
            )
            SQL);
        $this->addSql('CREATE INDEX delivery_campaign_state_idx ON communication.delivery (organizer_id, campaign_id, state)');
        $this->addSql('CREATE INDEX delivery_organizer_created_idx ON communication.delivery (organizer_id, created_at DESC)');
        $this->addSql("CREATE INDEX delivery_due_idx ON communication.delivery (send_after) WHERE state = 'scheduled' AND dispatched_at IS NULL");
        $this->addSql('CREATE INDEX delivery_order_idx ON communication.delivery (order_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE communication.suppression (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                email_sha256 CHAR(64) NOT NULL,
                scope VARCHAR(16) NOT NULL,
                reason VARCHAR(32) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT suppression_scope_check CHECK (scope IN ('marketing', 'all')),
                CONSTRAINT suppression_unique UNIQUE (organizer_id, email_sha256, scope)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE communication.reminder_settings (
                organizer_id UUID NOT NULL PRIMARY KEY REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                payment_offsets_hours JSONB NOT NULL,
                pre_event_hours INT NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT reminder_settings_pre_event_check CHECK (pre_event_hours BETWEEN 1 AND 336)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE communication.reminder_settings');
        $this->addSql('DROP TABLE communication.suppression');
        $this->addSql('DROP TABLE communication.delivery');
        $this->addSql('DROP TABLE communication.campaign');
        $this->addSql('DROP TABLE communication.message_template');
    }
}
