<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;

/**
 * `templateKey` names a message template (`activity_changed`, `program_changed`); the subject is rendered from it.
 */
final readonly class ProposedCampaign
{
    public function __construct(
        public EventId $eventId,
        public string $templateKey,
        public CampaignAudience $audience,
        public ?ActivityId $activityId,
    ) {}
}
