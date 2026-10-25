<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Console;

use Eventor\Registration\Application\Capacity\Query\CapacityConsistencyCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recomputes held and confirmed of every bucket from its claims and reports the buckets that drifted (ADR 002,
 * "CapacityConsistencyCheck"). Read-only: it never repairs anything, a drift is an incident to look at. Exit code 1
 * when anything differs, so a scheduler or CI can alert on it.
 *
 *     bin/console eventor:capacity:verify
 */
#[AsCommand(name: 'eventor:capacity:verify', description: 'Compare the held/confirmed counters of all capacity buckets with their claims and report drift')]
final readonly class VerifyCapacityCommand
{
    public function __construct(private CapacityConsistencyCheck $check) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $drift = $this->check->drift();
        if ([] === $drift) {
            $io->success('Capacity counters match the claims.');

            return Command::SUCCESS;
        }

        $io->table(
            ['bucket', 'event', 'held', 'held by claims', 'confirmed', 'confirmed by claims'],
            array_map(static fn($row): array => [$row->bucketId, $row->eventId, $row->held, $row->claimedHeld, $row->confirmed, $row->claimedConfirmed], $drift),
        );
        $io->error(\sprintf('%d bucket(s) drifted from their claims.', \count($drift)));

        return Command::FAILURE;
    }
}
