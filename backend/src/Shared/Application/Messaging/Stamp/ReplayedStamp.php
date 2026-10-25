<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Added by IdempotencyMiddleware when the result comes from an earlier identical request (no handler ran).
 * Controllers may expose it as `Idempotent-Replayed: true`.
 */
final readonly class ReplayedStamp implements StampInterface {}
