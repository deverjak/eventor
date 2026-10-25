<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Catalog;

use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\ActivityDetails;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Structure\EventDetails;
use Eventor\Catalog\Domain\Structure\EventVisibility;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Catalog\Domain\Structure\Variant;
use Eventor\Catalog\Domain\Structure\VariantDetails;
use Eventor\Catalog\Domain\Structure\Venue;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;

/**
 * Domain objects for unit tests.
 */
final class Build
{
    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-25 12:00:00+00:00');
    }

    public static function details(string $name = 'Borský podzimní víkend 2026'): EventDetails
    {
        return new EventDetails(
            $name,
            'Víkend v Borském parku',
            null,
            null,
            null,
            null,
            null,
            'Europe/Prague',
            new \DateTimeImmutable('2026-11-21 08:00:00+01:00'),
            new \DateTimeImmutable('2026-11-22 16:00:00+01:00'),
            null,
            EventVisibility::Public,
            [new Venue('Borský park', 'Plzeň', 'https://mapy.cz/borsky-park')],
            null,
            null,
        );
    }

    public static function event(?OrganizerId $organizer = null, string $slug = 'borsky-podzimni-vikend-2026', ?PaymentRules $rules = null): Event
    {
        return Event::create(EventId::generate(), $organizer ?? OrganizerId::generate(), $slug, self::details(), $rules ?? PaymentRules::defaults(), self::now());
    }

    public static function activity(Event $event, string $name = 'Trail 5 km', bool $minorsOnly = false, ?int $limit = null): Activity
    {
        return Activity::create(
            ActivityId::generate(),
            $event->organizerId(),
            $event->id(),
            new ActivityDetails($name, null, null, new \DateTimeImmutable('2026-11-21 10:00:00+01:00'), null, null, null, null, null, $minorsOnly),
            false,
            0,
            self::now(),
            $limit,
        );
    }

    public static function variant(Event $event, Activity $activity, int $priceMinor = 30000, ?int $teamSize = null, ?int $limit = null): Variant
    {
        return Variant::create(
            VariantId::generate(),
            $event->organizerId(),
            $event->id(),
            $activity->id(),
            new VariantDetails('Jednotlivec', Money::of($priceMinor), $teamSize, null, null),
            0,
            self::now(),
            $limit,
        );
    }
}
