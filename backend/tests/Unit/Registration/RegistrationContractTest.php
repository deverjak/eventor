<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration;

use Eventor\Registration\Contract\BucketOccupancy;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\UserInterface\Http\Problem\ProblemFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

final class RegistrationContractTest extends TestCase
{
    public function test_capacity_exhausted_becomes_a_409_problem_with_the_bucket_and_no_next_step_without_a_waitlist(): void
    {
        $bucket = CapacityBucketId::generate();

        $problem = (new ProblemFactory(new Translator('en')))->fromThrowable(new CapacityExhausted($bucket, CapacityScope::Variant), null);

        self::assertSame('capacity_exhausted', $problem->code);
        self::assertSame(409, $problem->status);
        self::assertSame(['bucketId' => $bucket->toString(), 'scope' => 'variant', 'waitlistAvailable' => false], $problem->context);
        self::assertSame([], $problem->nextSteps);
    }

    public function test_capacity_exhausted_offers_the_waitlist_when_it_is_available(): void
    {
        $problem = (new ProblemFactory(new Translator('en')))->fromThrowable(new CapacityExhausted(CapacityBucketId::generate(), CapacityScope::Activity, true), null);

        self::assertSame([['action' => 'join_waitlist']], $problem->nextSteps);
        self::assertTrue($problem->context['waitlistAvailable']);
    }

    public function test_free_places_of_a_bucket_never_go_below_zero_and_unlimited_is_null(): void
    {
        $id = CapacityBucketId::generate();

        self::assertSame(3, (new BucketOccupancy($id, CapacityScope::Event, 'e', 10, 4, 3, false))->available());
        self::assertSame(0, (new BucketOccupancy($id, CapacityScope::Event, 'e', 5, 4, 3, true))->available(), 'overbooked after lowering the limit (CAP-06)');
        self::assertNull((new BucketOccupancy($id, CapacityScope::Event, 'e', null, 4, 3, false))->available());
    }
}
