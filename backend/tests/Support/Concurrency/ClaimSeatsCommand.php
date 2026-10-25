<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Concurrency;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TEST ONLY (registered by the Registration module in the test environment): holds seats in the given buckets in
 * one transaction, the way a PlaceOrder handler would, and prints a JSON line with the outcome. Started N times at
 * once by ConcurrencyHarness (book chapter 10: `eventor:test:place-order`, here reduced to the capacity step).
 *
 *     bin/console eventor:test:capacity-claim --organizer=… --bucket=… --bucket=… --seats=1 --barrier=123
 */
#[AsCommand(name: 'eventor:test:capacity-claim', description: 'TEST ONLY: hold seats in buckets in one transaction (concurrency harness)')]
final readonly class ClaimSeatsCommand
{
    public function __construct(
        private Connection $connection,
        private CapacityAllocator $allocator,
    ) {}

    /**
     * @param list<string> $bucket
     */
    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Organizer id of the buckets')]
        string $organizer = '',
        #[Option(description: 'Bucket id; repeat for several buckets of one order. Order of the options = order handed to the allocator')]
        array $bucket = [],
        #[Option(description: 'Seats per bucket')]
        int $seats = 1,
        #[Option(description: 'held or confirmed')]
        string $kind = 'held',
        #[Option(description: 'Advisory-lock key of the barrier to wait for (set by ConcurrencyHarness)')]
        ?int $barrier = null,
    ): int {
        if (null !== $barrier) {
            AdvisoryBarrier::wait($this->connection, $barrier);
        }

        $participation = ParticipationId::generate();
        $claims = array_map(
            static fn(string $id): SeatClaim => new SeatClaim($participation, CapacityBucketId::fromString($id), $seats, ClaimKind::from($kind)),
            $bucket,
        );

        try {
            $this->connection->transactional(function () use ($organizer, $claims): void {
                $this->allocator->claim(OrganizerId::fromString($organizer), $claims);
            });
        } catch (CapacityExhausted $e) {
            return $this->report($output, ['ok' => false, 'code' => 'capacity_exhausted', 'bucketId' => $e->bucketId->toString()], Command::FAILURE);
        } catch (CapacityBucketNotFound) {
            return $this->report($output, ['ok' => false, 'code' => 'capacity_bucket_not_found'], Command::FAILURE);
        } catch (\Throwable $e) {
            return $this->report($output, ['ok' => false, 'code' => 'error', 'message' => $e::class.': '.$e->getMessage()], Command::FAILURE);
        }

        return $this->report($output, ['ok' => true, 'participationId' => $participation->toString()], Command::SUCCESS);
    }

    /**
     * @param array<string, mixed> $report
     */
    private function report(OutputInterface $output, array $report, int $exit): int
    {
        $output->writeln(json_encode($report, \JSON_THROW_ON_ERROR));

        return $exit;
    }
}
