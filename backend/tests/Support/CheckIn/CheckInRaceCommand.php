<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\CheckIn;

use Doctrine\DBAL\Connection;
use Eventor\CheckIn\Application\RecordCheckIn\RecordCheckIn;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\Concurrency\AdvisoryBarrier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * TEST ONLY (registered by the CheckIn module in the test environment): the operator at the desk, for S10. Waits for
 * the starting gun of ConcurrencyHarness, then sends the same `RecordCheckIn` through the real command bus (transaction,
 * advisory lock, unique index) and prints a JSON line with the outcome: `{"ok":true}` or `{"ok":false,"code":"already_checked_in"}`.
 *
 *     bin/console eventor:test:check-in --organizer=… --event=… --list=… --participation=… --operator=… --barrier=123
 */
#[AsCommand(name: 'eventor:test:check-in', description: 'TEST ONLY: check a participant in (concurrency harness, S10)')]
final readonly class CheckInRaceCommand
{
    public function __construct(
        private Connection $connection,
        private CommandBus $commands,
    ) {}

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Organizer id')]
        string $organizer = '',
        #[Option(description: 'Event id')]
        string $event = '',
        #[Option(description: 'Check-in list id')]
        string $list = '',
        #[Option(description: 'Participation id')]
        string $participation = '',
        #[Option(description: 'Account id of the operator (any string, it is only stored)')]
        string $operator = 'operator',
        #[Option(description: 'Advisory-lock key of the barrier to wait for (set by ConcurrencyHarness)')]
        ?int $barrier = null,
    ): int {
        if (null !== $barrier) {
            AdvisoryBarrier::wait($this->connection, $barrier);
        }

        try {
            // The participants of the race are unpaid in the eyes of Payments (no order finance), so the warning is acknowledged.
            $result = $this->commands->dispatch(
                new RecordCheckIn($event, $list, $participation, null, true, $operator, null),
                TenantStamp::of(OrganizerId::fromString($organizer)),
            );
        } catch (\Throwable $e) {
            $problem = (new \ReflectionClass($e))->getAttributes(AsProblem::class);
            $code = [] === $problem ? 'error' : $problem[0]->newInstance()->code;

            return $this->report($output, [
                'ok' => false,
                'code' => $code,
                'context' => $e instanceof DomainException ? $e->context() : [],
                'message' => $e::class.': '.$e->getMessage(),
            ], Command::FAILURE);
        }

        return $this->report($output, ['ok' => true, 'result' => $result], Command::SUCCESS);
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
