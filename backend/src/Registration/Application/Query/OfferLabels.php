<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Names of activities and variants for the read models, taken from the published offer of Catalog (one lookup per
 * event and instance; Catalog caches the snapshot itself). An event that has no offer any more shows empty names.
 */
final class OfferLabels implements ResetInterface
{
    /** @var array<string, array{activities: array<string, array{name: string, startsAt: ?\DateTimeImmutable}>, variants: array<string, string>}> */
    private array $cache = [];

    public function __construct(private readonly EventCatalog $catalog) {}

    public function reset(): void
    {
        $this->cache = [];
    }

    public function activityName(OrganizerId $organizer, EventId $event, ActivityId $activity): string
    {
        return $this->of($organizer, $event)['activities'][$activity->toString()]['name'] ?? '';
    }

    public function activityStart(OrganizerId $organizer, EventId $event, ActivityId $activity): ?\DateTimeImmutable
    {
        return $this->of($organizer, $event)['activities'][$activity->toString()]['startsAt'] ?? null;
    }

    public function variantName(OrganizerId $organizer, EventId $event, VariantId $variant): string
    {
        return $this->of($organizer, $event)['variants'][$variant->toString()] ?? '';
    }

    /**
     * @return array{activities: array<string, array{name: string, startsAt: ?\DateTimeImmutable}>, variants: array<string, string>}
     */
    private function of(OrganizerId $organizer, EventId $event): array
    {
        $key = $organizer->toString().'|'.$event->toString();
        if (!isset($this->cache[$key])) {
            $labels = ['activities' => [], 'variants' => []];
            try {
                foreach ($this->catalog->registrationSnapshot($organizer, $event)->activities as $activity) {
                    $labels['activities'][$activity->id->toString()] = ['name' => $activity->name, 'startsAt' => $activity->startsAt];
                    foreach ($activity->variants as $variant) {
                        $labels['variants'][$variant->id->toString()] = $variant->name;
                    }
                }
            } catch (EventNotFound) {
            }
            $this->cache[$key] = $labels;
        }

        return $this->cache[$key];
    }
}
