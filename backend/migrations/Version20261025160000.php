<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Payments: the append-only ledger, its cached fold, the mirror of Pairrot payment requests, refund tasks,
 * the reconciliation queue, the webhook inbox and the organizer -> Pairrot account mapping (chapter 03, "Platby").
 *
 * `order_id` is a plain uuid (no foreign key): the `registration.order` table belongs to another module that is
 * built in parallel; the id is the contract between the two (chapter 02).
 */
final class Version20261025160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Payments: ledger_entry (append-only), order_finance, payment_request, refund_task, unmatched_transaction, inbox_message, organizer_pairrot_account';
    }

    public function up(Schema $schema): void
    {
        // The mirror of a request in Pairrot (zdroj pravdy je Pairrot; eventor si drží poslední známý stav).
        $this->addSql(<<<'SQL'
            CREATE TABLE payments.payment_request (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                order_id UUID NOT NULL,
                attempt INT NOT NULL,
                purpose VARCHAR(16) NOT NULL,
                external_id VARCHAR(200) NOT NULL,
                creation_state VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL,
                amount_minor BIGINT NOT NULL,
                amount_currency CHAR(3) NOT NULL,
                message VARCHAR(60) NOT NULL,
                due_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                subtenant_id VARCHAR(64) NOT NULL,
                bank_connection_id VARCHAR(64) NOT NULL,
                pairrot_id VARCHAR(64) DEFAULT NULL,
                variable_symbol VARCHAR(10) DEFAULT NULL,
                account_number VARCHAR(40) DEFAULT NULL,
                iban VARCHAR(34) DEFAULT NULL,
                spayd TEXT DEFAULT NULL,
                known_paid_minor BIGINT NOT NULL DEFAULT 0,
                last_sequence INT NOT NULL DEFAULT 0,
                failed_attempts INT NOT NULL DEFAULT 0,
                last_error VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                issued_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT payment_request_purpose_check CHECK (purpose IN ('initial', 'retry', 'supplementary')),
                CONSTRAINT payment_request_creation_state_check CHECK (creation_state IN ('PENDING', 'CREATED', 'FAILED')),
                CONSTRAINT payment_request_status_check CHECK (status IN ('requested', 'issued', 'open', 'partially_paid', 'paid', 'overpaid', 'cancelled', 'failed')),
                CONSTRAINT payment_request_amount_positive CHECK (amount_minor > 0),
                CONSTRAINT payment_request_issued_has_pairrot_id CHECK (creation_state <> 'CREATED' OR pairrot_id IS NOT NULL)
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX payment_request_external_id_key ON payments.payment_request (external_id)');
        $this->addSql('CREATE UNIQUE INDEX payment_request_order_attempt_key ON payments.payment_request (order_id, attempt)');
        $this->addSql('CREATE UNIQUE INDEX payment_request_pairrot_id_key ON payments.payment_request (pairrot_id) WHERE pairrot_id IS NOT NULL');
        $this->addSql('CREATE INDEX payment_request_organizer_order_idx ON payments.payment_request (organizer_id, order_id)');
        $this->addSql('CREATE INDEX payment_request_vs_idx ON payments.payment_request (organizer_id, variable_symbol) WHERE variable_symbol IS NOT NULL');

        // The ledger: append-only, the only truth about money (ADR 001).
        $this->addSql(<<<'SQL'
            CREATE TABLE payments.ledger_entry (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                order_id UUID NOT NULL,
                kind VARCHAR(32) NOT NULL,
                amount_minor BIGINT NOT NULL,
                amount_currency CHAR(3) NOT NULL,
                source VARCHAR(16) NOT NULL,
                source_ref VARCHAR(190) DEFAULT NULL,
                compensates_id UUID DEFAULT NULL REFERENCES payments.ledger_entry (id) ON DELETE RESTRICT,
                payment_request_id UUID DEFAULT NULL REFERENCES payments.payment_request (id) ON DELETE RESTRICT,
                station_id UUID DEFAULT NULL,
                actor JSONB NOT NULL,
                reason TEXT DEFAULT NULL,
                occurred_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                recorded_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                CONSTRAINT ledger_entry_kind_check CHECK (kind IN ('pairrot_allocation', 'pairrot_revocation', 'cash', 'cash_correction', 'manual_correction', 'refund_settled')),
                CONSTRAINT ledger_entry_source_check CHECK (source IN ('pairrot', 'cash', 'manual')),
                CONSTRAINT ledger_entry_source_matches_kind CHECK (
                    (kind IN ('pairrot_allocation', 'pairrot_revocation') AND source = 'pairrot')
                    OR (kind IN ('cash', 'cash_correction') AND source = 'cash')
                    OR (kind IN ('manual_correction', 'refund_settled') AND source = 'manual')
                ),
                CONSTRAINT ledger_entry_amount_sign CHECK (
                    amount_minor <> 0 AND (
                        (kind IN ('pairrot_allocation', 'cash') AND amount_minor > 0)
                        OR (kind IN ('pairrot_revocation', 'refund_settled') AND amount_minor < 0)
                        OR kind IN ('cash_correction', 'manual_correction')
                    )
                ),
                CONSTRAINT ledger_entry_cash_correction_compensates CHECK (kind <> 'cash_correction' OR compensates_id IS NOT NULL),
                CONSTRAINT ledger_entry_manual_correction_reason CHECK (kind NOT IN ('cash_correction', 'manual_correction') OR (reason IS NOT NULL AND reason <> ''))
            )
            SQL);
        // S03: the same Pairrot event (or the same cash request) is booked once, whatever the number of deliveries.
        $this->addSql('CREATE UNIQUE INDEX ledger_entry_source_ref_key ON payments.ledger_entry (organizer_id, source, source_ref) WHERE source_ref IS NOT NULL');
        // S11: a cash entry is compensated at most once.
        $this->addSql("CREATE UNIQUE INDEX ledger_entry_one_correction_key ON payments.ledger_entry (compensates_id) WHERE kind = 'cash_correction'");
        $this->addSql('CREATE INDEX ledger_entry_order_idx ON payments.ledger_entry (order_id, recorded_at)');
        $this->addSql('REVOKE UPDATE, DELETE, TRUNCATE ON payments.ledger_entry FROM eventor_app');

        // Cache of the fold of the ledger against the amount due (chapter 05); rewritten in the transaction of every entry.
        $this->addSql(<<<'SQL'
            CREATE TABLE payments.order_finance (
                order_id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                payment_method VARCHAR(16) NOT NULL,
                currency CHAR(3) NOT NULL,
                state VARCHAR(16) NOT NULL,
                due_minor BIGINT NOT NULL,
                received_minor BIGINT NOT NULL DEFAULT 0,
                received_transfer_minor BIGINT NOT NULL DEFAULT 0,
                received_cash_minor BIGINT NOT NULL DEFAULT 0,
                refunded_minor BIGINT NOT NULL DEFAULT 0,
                disputed BOOLEAN NOT NULL DEFAULT FALSE,
                due_change_reason VARCHAR(32) DEFAULT NULL,
                ledger_version INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT order_finance_state_check CHECK (state IN ('unpaid', 'partially_paid', 'paid', 'overpaid', 'disputed', 'refund_pending', 'refunded', 'not_required')),
                CONSTRAINT order_finance_method_check CHECK (payment_method IN ('bank_transfer', 'cash', 'free')),
                CONSTRAINT order_finance_due_not_negative CHECK (due_minor >= 0)
            )
            SQL);
        $this->addSql('CREATE INDEX order_finance_organizer_state_idx ON payments.order_finance (organizer_id, state)');

        $this->addSql(<<<'SQL'
            CREATE TABLE payments.refund_task (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                order_id UUID NOT NULL,
                amount_minor BIGINT NOT NULL,
                amount_currency CHAR(3) NOT NULL,
                reason VARCHAR(16) NOT NULL,
                status VARCHAR(16) NOT NULL,
                note TEXT DEFAULT NULL,
                created_by JSONB NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                settled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                settled_by JSONB DEFAULT NULL,
                settle_method VARCHAR(32) DEFAULT NULL,
                settle_reference VARCHAR(190) DEFAULT NULL,
                settle_note TEXT DEFAULT NULL,
                ledger_entry_id UUID DEFAULT NULL REFERENCES payments.ledger_entry (id) ON DELETE RESTRICT,
                cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT refund_task_reason_check CHECK (reason IN ('cancellation', 'overpayment', 'event_cancelled', 'late_payment')),
                CONSTRAINT refund_task_status_check CHECK (status IN ('OPEN', 'SETTLED', 'CANCELLED')),
                CONSTRAINT refund_task_amount_positive CHECK (amount_minor > 0),
                CONSTRAINT refund_task_settled_has_entry CHECK (status <> 'SETTLED' OR ledger_entry_id IS NOT NULL)
            )
            SQL);
        $this->addSql("CREATE UNIQUE INDEX refund_task_one_open_per_order_key ON payments.refund_task (order_id) WHERE status = 'OPEN'");
        $this->addSql('CREATE INDEX refund_task_organizer_status_idx ON payments.refund_task (organizer_id, status, created_at)');

        $this->addSql(<<<'SQL'
            CREATE TABLE payments.unmatched_transaction (
                id UUID NOT NULL PRIMARY KEY,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                pairrot_tx_id VARCHAR(64) NOT NULL,
                amount_minor BIGINT NOT NULL,
                amount_currency CHAR(3) NOT NULL,
                variable_symbol VARCHAR(10) DEFAULT NULL,
                booked_on DATE NOT NULL,
                matching_result VARCHAR(32) NOT NULL,
                suggested_request_pairrot_id VARCHAR(64) DEFAULT NULL,
                handling_state VARCHAR(16) NOT NULL,
                last_sequence INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                resolved_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                resolution VARCHAR(16) DEFAULT NULL,
                resolved_order_id UUID DEFAULT NULL,
                resolved_payment_request_id UUID DEFAULT NULL REFERENCES payments.payment_request (id) ON DELETE RESTRICT,
                allocation_requested_by JSONB DEFAULT NULL,
                allocation_reason TEXT DEFAULT NULL,
                CONSTRAINT unmatched_transaction_matching_result_check CHECK (matching_result IN ('missing_variable_symbol', 'request_not_found', 'amount_mismatch', 'request_already_paid', 'request_cancelled')),
                CONSTRAINT unmatched_transaction_resolution_check CHECK (resolution IS NULL OR resolution IN ('allocated', 'ignored', 'closed'))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX unmatched_transaction_pairrot_tx_id_key ON payments.unmatched_transaction (pairrot_tx_id)');
        $this->addSql('CREATE INDEX unmatched_transaction_open_idx ON payments.unmatched_transaction (organizer_id, resolved_at, booked_on)');

        // Global (no organizer): the webhook arrives before the tenant is known. Retention 90 days (chapter 03).
        $this->addSql(<<<'SQL'
            CREATE TABLE payments.inbox_message (
                id UUID NOT NULL PRIMARY KEY,
                provider VARCHAR(32) NOT NULL,
                message_id VARCHAR(190) NOT NULL,
                type VARCHAR(100) NOT NULL,
                payload JSONB NOT NULL,
                status VARCHAR(16) NOT NULL,
                organizer_id UUID DEFAULT NULL,
                error VARCHAR(255) DEFAULT NULL,
                received_at TIMESTAMP(6) WITH TIME ZONE NOT NULL,
                processed_at TIMESTAMP(6) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT inbox_message_status_check CHECK (status IN ('pending', 'processed', 'rejected'))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX inbox_message_provider_message_key ON payments.inbox_message (provider, message_id)');
        $this->addSql('CREATE INDEX inbox_message_received_idx ON payments.inbox_message (received_at)');

        // Not secret: ids only (ADR 004). Reverse lookup subtenant -> organizer for transaction and review events.
        $this->addSql(<<<'SQL'
            CREATE TABLE payments.organizer_pairrot_account (
                organizer_id UUID NOT NULL PRIMARY KEY REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                subtenant_id VARCHAR(64) NOT NULL,
                subtenant_external_id VARCHAR(200) NOT NULL,
                bank_connection_id VARCHAR(64) DEFAULT NULL,
                masked_iban VARCHAR(40) DEFAULT NULL,
                inbound_address VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX organizer_pairrot_account_subtenant_key ON payments.organizer_pairrot_account (subtenant_id)');
        $this->addSql('CREATE UNIQUE INDEX organizer_pairrot_account_connection_key ON payments.organizer_pairrot_account (bank_connection_id) WHERE bank_connection_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payments.organizer_pairrot_account');
        $this->addSql('DROP TABLE payments.inbox_message');
        $this->addSql('DROP TABLE payments.unmatched_transaction');
        $this->addSql('DROP TABLE payments.refund_task');
        $this->addSql('DROP TABLE payments.order_finance');
        $this->addSql('DROP TABLE payments.ledger_entry');
        $this->addSql('DROP TABLE payments.payment_request');
    }
}
