<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * A QR token with a valid signature and a current version.
 */
final readonly class VerifiedQr
{
    public function __construct(
        public ParticipationId $participationId,
        public int $qrVersion,
    ) {}
}
