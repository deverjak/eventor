<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Attention: base of the "needs a human" queue, used by the dead-letter listener.
 */
final class Version20261025090300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attention: attention_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE attention.attention_item (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID DEFAULT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                type VARCHAR(64) NOT NULL,
                dedupe_key VARCHAR(255) NOT NULL,
                subject_type VARCHAR(64) DEFAULT NULL,
                subject_id VARCHAR(128) DEFAULT NULL,
                payload JSONB NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                state VARCHAR(16) NOT NULL,
                resolved_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT attention_item_state_check CHECK (state IN ('OPEN', 'ASSIGNED', 'RESOLVED')),
                CONSTRAINT attention_item_type_check CHECK (type IN (
                    'late_payment', 'overpayment', 'unmatched_payment', 'payment_revoked', 'pairrot_review',
                    'payment_request_issue_failed', 'integration_degraded', 'integration_inconsistency', 'refund_task',
                    'consent_withdrawn', 'missing_confirmation', 'cancellation_request', 'delivery_failed',
                    'outbox_dead_letter', 'scheduled_job_failing', 'capacity_drift'
                ))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX attention_item_dedupe_key_key ON attention.attention_item (dedupe_key)');
        $this->addSql('CREATE INDEX attention_item_open_idx ON attention.attention_item (organizer_id, state)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE attention.attention_item');
    }
}
