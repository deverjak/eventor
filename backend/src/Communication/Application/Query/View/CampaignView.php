<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

/**
 * A campaign in a list.
 */
final readonly class CampaignView
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
        public ?\DateTimeImmutable $scheduledAt,
        public \DateTimeImmutable $createdAt,
        public array $deliveries,
    ) {}
}
