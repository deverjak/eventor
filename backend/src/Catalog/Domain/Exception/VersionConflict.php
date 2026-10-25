<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * Optimistic lock: the client edited a stale copy (chapter 03, "Optimistický zámek"). The UI offers a refresh.
 */
#[AsProblem(code: 'version_conflict', status: 409)]
final class VersionConflict extends DomainException
{
    public static function for(string $what, int $expected, int $actual): self
    {
        return new self(\sprintf('%s was changed by someone else (you edited version %d, the current one is %d). Reload and apply your change again.', $what, $expected, $actual));
    }
}
