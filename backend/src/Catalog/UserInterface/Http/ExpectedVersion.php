<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Optimistic locking over HTTP (chapter 03): a client that edited version 3 sends `If-Match: 3`; a stale write is a
 * 409 `version_conflict`. Without the header the write is unconditional.
 */
final class ExpectedVersion
{
    public static function from(Request $request): ?int
    {
        $header = $request->headers->get('If-Match');
        if (null === $header) {
            return null;
        }
        $digits = trim($header, " \t\"W/");

        return ctype_digit($digits) ? (int) $digits : null;
    }
}
