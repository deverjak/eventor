<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Attention;

/**
 * `attention_item.type` values (chapter 06, "Typy výjimek"). The same list is a CHECK constraint in the database.
 */
enum AttentionType: string
{
    case LatePayment = 'late_payment';
    case Overpayment = 'overpayment';
    case UnmatchedPayment = 'unmatched_payment';
    case PaymentRevoked = 'payment_revoked';
    case PairrotReview = 'pairrot_review';
    case PaymentRequestIssueFailed = 'payment_request_issue_failed';
    case IntegrationDegraded = 'integration_degraded';
    case IntegrationInconsistency = 'integration_inconsistency';
    case RefundTask = 'refund_task';
    case ConsentWithdrawn = 'consent_withdrawn';
    case MissingConfirmation = 'missing_confirmation';
    case CancellationRequest = 'cancellation_request';
    case DeliveryFailed = 'delivery_failed';
    case OutboxDeadLetter = 'outbox_dead_letter';
    case ScheduledJobFailing = 'scheduled_job_failing';
    case CapacityDrift = 'capacity_drift';
}
