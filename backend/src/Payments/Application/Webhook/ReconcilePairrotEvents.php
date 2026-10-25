<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * Scheduled every 15 minutes (`ReconcilePairrotEvents`, chapter 07): reads the Pairrot event log of the last 48 hours
 * and feeds every event into the inbox. Pairrot retries a webhook for about an hour only; this closes the gap.
 */
final readonly class ReconcilePairrotEvents {}
