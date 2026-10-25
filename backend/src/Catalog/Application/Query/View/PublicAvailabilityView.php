<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * Free places by event, activity and variant (the maps are keyed by id).
 */
final readonly class PublicAvailabilityView
{
    /**
     * @param array<string, AvailabilityStatusView> $activities
     * @param array<string, AvailabilityStatusView> $variants
     */
    public function __construct(
        public string $eventId,
        public bool $shown,
        public ?AvailabilityStatusView $event,
        public array $activities,
        public array $variants,
    ) {}
}
