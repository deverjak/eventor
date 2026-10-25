<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The QR token is malformed, has a bad signature or an outdated version (CHK-03).
 */
#[AsProblem(code: 'qr_token_invalid', status: 422)]
final class InvalidQrToken extends DomainException
{
    public static function badSignature(): self
    {
        return new self('The QR code is not valid.');
    }

    public static function outdated(): self
    {
        return new self('The QR code was replaced by a newer one.');
    }
}
