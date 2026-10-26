<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Shared\Domain\Capacity\CapacityScope;

/**
 * A reclaim over event + activity + variant buckets is all or nothing for the participation: when the variant is full again
 * the event and activity seats it already took are given back, so a caller that handles the exception leaks nothing.
 */
final class ReclaimIsAtomicTest extends CapacityTestCase
{
    public function test_a_full_variant_bucket_leaves_the_event_and_activity_counters_untouched(): void
    {
        $event = $this->bucket(10, CapacityScope::Event);
        $activity = $this->bucket(10, CapacityScope::Activity);
        $variant = $this->bucket(1, CapacityScope::Variant);
        $expired = $this->claim([$event, $activity, $variant], kind: ClaimKind::Held);
        $this->allocator()->release($this->organizer, [$expired]);
        $this->claim([$variant], kind: ClaimKind::Confirmed); // somebody took the last variant seat
        $before = [$this->counters($event), $this->counters($activity), $this->counters($variant)];

        try {
            $this->allocator()->reclaim($this->organizer, $expired, ClaimKind::Confirmed, false);
            self::fail('The variant bucket is full.');
        } catch (CapacityExhausted $e) {
            self::assertSame(CapacityScope::Variant, $e->scope);
        }

        self::assertSame($before, [$this->counters($event), $this->counters($activity), $this->counters($variant)]);
        self::assertSame('released', $this->claimStatus($expired, $event));
        self::assertSame('released', $this->claimStatus($expired, $activity));
        self::assertSame('released', $this->claimStatus($expired, $variant));
        $this->assertNoDrift();
    }
}
