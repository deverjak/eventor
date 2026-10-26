<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\ValueResolver;

use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * The participation a desk request is about, however the operator identified it: a scanned QR token, a typed
 * registration code or an id picked from the search results.
 */
final readonly class ResolvedParticipation
{
    public function __construct(public ParticipationId $id) {}
}
