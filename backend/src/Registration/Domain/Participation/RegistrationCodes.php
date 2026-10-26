<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Participation;

/**
 * Human-friendly registration codes (typed at the check-in desk, CHK-01): unique per organizer, no look-alike
 * characters. The unique index is the final judge; the generator only avoids the obvious collision.
 */
interface RegistrationCodes
{
    public function next(): string;
}
