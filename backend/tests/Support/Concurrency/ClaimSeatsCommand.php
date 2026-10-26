<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Concurrency;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Registration\Contract\Exception\CapacityExhausted;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Capacity\ClaimKind;
use Eventor\Registration\Domain\Capacity\SeatClaim;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CapacityBucketId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
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
            $this->connection->transactional(function () use ($organizer, $claims, $participation, $kind): void {
                $this->insertParticipation($organizer, $participation, ClaimKind::Held->value === $kind);
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
     * The claim rows reference the participation (deferred foreign key, checked at COMMIT), so the process writes the
     * minimal order -> person -> participation chain a real `PlaceOrder` would, in the same transaction as the claim.
     */
    private function insertParticipation(string $organizer, ParticipationId $participation, bool $held): void
    {
        $order = OrderId::generate()->toString();
        $person = PersonId::generate()->toString();
        $now = '2026-11-01 12:00:00+00';
        $this->connection->executeStatement(
            'INSERT INTO registration.orders (id, organizer_id, event_id, number, orderer_email, payment_method, amount_due_minor, amount_due_currency, form_version_id, placed_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [$order, $organizer, EventId::generate()->toString(), 'T-'.substr($order, -12), 'race@example.test', 'cash', 'CZK', FormVersionId::generate()->toString(), $now],
        );
        $this->connection->executeStatement('INSERT INTO registration.person (id, organizer_id, order_id) VALUES (?, ?, ?)', [$person, $organizer, $order]);
        $this->connection->executeStatement(
            'INSERT INTO registration.participation (id, organizer_id, order_id, person_id, event_id, activity_id, variant_id, state, hold_expires_at, registration_code, price_minor, price_currency, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)',
            [$participation->toString(), $organizer, $order, $person, EventId::generate()->toString(), ActivityId::generate()->toString(), VariantId::generate()->toString(), $held ? 'pending' : 'confirmed', $held ? '2026-12-01 12:00:00+00' : null, strtoupper(substr($participation->toString(), -10)), 'CZK', $now],
        );
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
