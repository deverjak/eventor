<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Payments\Contract\Event\IntegrationDegraded;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Payments reports every integration trouble as `integration_degraded` with a reason code. Outages (circuit breaker,
 * failing reconcile, paused inbound address) stay `integration_degraded`, one item per integration. Anomalies of
 * a single payment become `integration_inconsistency` (one item per integration and reason), and a review case
 * opened by Pairrot becomes `pairrot_review`. A platform-wide outage (no organizer) is stored without a tenant.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnIntegrationDegraded
{
    private const array INCONSISTENCIES = ['unknown_payment_request', 'request_cancelled_in_pairrot', 'message_rejected'];
    private const string REVIEW_CASE = 'pairrot_review_case_opened';

    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(IntegrationDegraded $event): void
    {
        $payload = ['reason' => $event->reason, 'since' => $event->since->format(\DATE_ATOM)];
        $subject = $event->integration.':'.$event->reason;

        if (\in_array($event->reason, self::INCONSISTENCIES, true)) {
            $this->attention->open(AttentionType::IntegrationInconsistency, $event->organizerId, 'integration', $subject, $payload);
        } elseif (self::REVIEW_CASE === $event->reason) {
            $this->attention->open(AttentionType::PairrotReview, $event->organizerId, 'integration', $subject, $payload);
        } else {
            $this->attention->open(AttentionType::IntegrationDegraded, $event->organizerId, 'integration', $event->integration, $payload);
        }
    }
}
