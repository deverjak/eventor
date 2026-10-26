<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Expire;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Finds the orders whose reservations are over (cross-tenant on purpose: the sweeper serves every organizer) and expires
 * them one order at a time, each as the system actor inside its tenant. One order that fails is logged and skipped, so
 * it cannot stop the expiry of the others; the next tick tries it again.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('The sweeper itself changes nothing; the commands it dispatches record the events.')]
final readonly class ExpireReservationsHandler
{
    public function __construct(
        private Connection $connection,
        private CommandBus $commands,
        private AuditContext $audit,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExpireReservations $message): int
    {
        /** @var list<array{organizer_id: string, order_id: string}> $due */
        $due = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT organizer_id, order_id FROM registration.participation
              WHERE state = 'pending' AND hold_expires_at <= :now
              ORDER BY order_id LIMIT :limit",
            ['now' => \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:sP'), 'limit' => ExpireReservations::BATCH],
        );

        $handled = 0;
        foreach ($due as $row) {
            $organizer = OrganizerId::fromString($row['organizer_id']);
            $command = new ExpireOrderReservations($organizer, OrderId::fromString($row['order_id']));
            try {
                $this->audit->as(AuditActor::system('registration.expire_reservations'), fn(): mixed => $this->commands->dispatch($command, TenantStamp::of($organizer)));
                ++$handled;
            } catch (\Throwable $e) {
                $this->em->clear();
                $this->logger->error('Expiring the reservations of order {order} failed.', ['order' => $row['order_id'], 'exception' => $e]);
            }
        }

        return $handled;
    }
}
