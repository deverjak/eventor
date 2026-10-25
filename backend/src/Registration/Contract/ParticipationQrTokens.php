<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\InvalidQrToken;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The QR token of a participation, `qr1.<payload>.<sig>` (chapter 07/08). Registration issues it (self-service, e-mail),
 * CheckIn verifies it. The signature covers the participation id and `qr_version`; cancelling or transferring bumps
 * the version, so old codes die without a revocation list (CHK-03).
 */
interface ParticipationQrTokens
{
    /**
     * @throws ParticipationNotFound
     */
    public function issue(OrganizerId $organizer, ParticipationId $id): string;

    /**
     * Checks the signature and that the version is current. The participation state is checked by the caller.
     *
     * @throws InvalidQrToken
     */
    public function verify(OrganizerId $organizer, string $token): VerifiedQr;
}
