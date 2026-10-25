<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Structure;

use Eventor\Catalog\Domain\Exception\VariantNotFound;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;

interface VariantRepository
{
    /**
     * @throws VariantNotFound when the variant does not exist in this event
     */
    public function get(EventId $event, VariantId $id): Variant;

    /**
     * @return list<Variant> ordered by activity and position
     */
    public function forEvent(EventId $event): array;

    /**
     * @return list<Variant>
     */
    public function forActivity(ActivityId $activity): array;

    public function nextPosition(ActivityId $activity): int;

    public function add(Variant $variant): void;

    public function remove(Variant $variant): void;
}
