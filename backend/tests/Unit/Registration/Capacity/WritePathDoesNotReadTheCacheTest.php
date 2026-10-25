<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration\Capacity;

use Eventor\Registration\Application\Capacity\AnnounceRaisedLimit\AnnounceRaisedLimitHandler;
use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Application\Capacity\Query\CapacityBucketQuery;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Infrastructure\Capacity\DbalCapacityAllocator;
use Eventor\Registration\Infrastructure\Capacity\DbalCapacityProvisioner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * "The cache must never be read on the write path" as a structural guard: nothing that writes counters, decides a
 * claim or computes a limit change may depend on the cached reader or on any cache.
 */
final class WritePathDoesNotReadTheCacheTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function writePath(): iterable
    {
        yield 'allocator' => [DbalCapacityAllocator::class];
        yield 'provisioner' => [DbalCapacityProvisioner::class];
        yield 'releaser' => [CapacityReleaser::class];
        yield 'announce handler' => [AnnounceRaisedLimitHandler::class];
        yield 'exact counters query' => [CapacityBucketQuery::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('writePath')]
    public function test_it_depends_on_no_cache_and_not_on_the_availability_reader(string $class): void
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            $name = $type->getName();
            self::assertFalse(is_a($name, AvailabilityReader::class, true), "$class takes the AvailabilityReader");
            self::assertFalse(is_a($name, CacheInterface::class, true), "$class takes a cache");
        }
    }
}
