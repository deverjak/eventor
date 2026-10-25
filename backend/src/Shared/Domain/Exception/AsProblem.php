<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Exception;

/**
 * Maps a domain exception to an RFC 9457 problem (chapter 08, "Chyby"). The HTTP layer reads it with reflection;
 * the domain stays free of HTTP types. The title is translated by the key `problem.<code>.title`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsProblem
{
    public function __construct(
        public string $code,
        public int $status,
    ) {}
}
