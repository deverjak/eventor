<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\VariantId;

/**
 * A participant in the text of a message ("Anna, 5 km"). Personal data.
 */
final readonly class ParticipantRef
{
    public function __construct(
        public ParticipationId $participationId,
        public string $displayName,
        public ActivityId $activityId,
        public VariantId $variantId,
        public ParticipationState $state,
    ) {}
}
