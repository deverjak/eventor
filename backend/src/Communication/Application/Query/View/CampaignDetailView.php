<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

use Eventor\Communication\Contract\CampaignAudienceSpec;

/**
 * A campaign with its wording, its targeting and how its deliveries stand (COM-03, COM-06).
 */
final readonly class CampaignDetailView
{
    /**
     * @param array<string, int> $deliveries count per delivery state
     */
    public function __construct(
        public string $id,
        public string $eventId,
        public string $name,
        public string $purpose,
        public string $state,
        public ?string $templateKey,
        public string $locale,
        public string $subject,
        public string $body,
        public CampaignAudienceSpec $audience,
        public ?\DateTimeImmutable $scheduledAt,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $completedAt,
        public \DateTimeImmutable $createdAt,
        public array $deliveries,
    ) {}
}
