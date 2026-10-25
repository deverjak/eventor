<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayException;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Application\Port\ReconcileQuery;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Monolog\Attribute\WithMonologChannel;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `ReconcilePairrotEvents` (chapter 07, "Dorovnávací job"):
 *  1. page through `GET /v1/management/webhook-events?from=now-48h` and put every event into the inbox under the id a
 *     webhook would have carried: duplicates end on the unique key, new ones are applied by the normal handler;
 *  2. re-fetch the open requests issued more than 24 h ago and, when Pairrot's `payment_change_sequence` is ahead of
 *     ours, feed a synthetic `payment_request.snapshot` through the same path (same delta logic).
 * Idempotent and lock-free: concurrent runs only compete for the unique key. A Pairrot outage is not an error of the
 * job; the circuit breaker already reports it and the next run (15 minutes) tries again.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Job: it only feeds the inbox; the messages it queues record the domain events when they are applied.')]
#[WithMonologChannel('pairrot')]
final readonly class ReconcilePairrotEventsHandler
{
    private const int MAX_PAGES = 200;
    private const int SNAPSHOT_BATCH = 100;

    public function __construct(
        private PaymentGateway $gateway,
        private InboxIntake $intake,
        private ReconcileQuery $query,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{events: int, new: int, snapshots: int}
     */
    public function __invoke(ReconcilePairrotEvents $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $seen = 0;
        $new = 0;
        $snapshots = 0;

        try {
            $cursor = null;
            for ($page = 0; $page < self::MAX_PAGES; ++$page) {
                $events = $this->gateway->listWebhookEvents($now->modify('-48 hours'), $cursor);
                foreach ($events->events as $event) {
                    ++$seen;
                    if ('accepted' === $this->intake->accept($event)) {
                        ++$new;
                    }
                }
                $cursor = $events->nextCursor;
                if (null === $cursor) {
                    break;
                }
            }

            foreach ($this->query->openRequestsIssuedBefore($now->modify('-24 hours'), self::SNAPSHOT_BATCH) as $open) {
                $snapshot = $this->gateway->getRequest($open['subtenantId'], $open['pairrotId']);
                if ($snapshot->paymentChangeSequence <= $open['lastSequence']) {
                    continue;
                }
                $accepted = $this->intake->accept([
                    'id' => \sprintf('snapshot:%s:%d', $open['pairrotId'], $snapshot->paymentChangeSequence),
                    'type' => 'payment_request.snapshot',
                    'schema_version' => 1,
                    'created_at' => $now->format('Y-m-d\TH:i:s.u\Z'),
                    'data' => [
                        'payment_request_id' => $open['pairrotId'],
                        'external_id' => $open['externalId'],
                        'sequence' => $snapshot->paymentChangeSequence,
                        'cause' => 'snapshot',
                        'current' => ['status' => $snapshot->status, 'paid_amount' => $snapshot->paidAmountMinor],
                    ],
                ]);
                if ('accepted' === $accepted) {
                    ++$snapshots;
                }
            }
        } catch (PaymentGatewayException $e) {
            $this->logger->warning('Pairrot reconcile skipped.', ['error' => $e->errorCode(), 'events' => $seen]);
        }

        return ['events' => $seen, 'new' => $new, 'snapshots' => $snapshots];
    }
}
