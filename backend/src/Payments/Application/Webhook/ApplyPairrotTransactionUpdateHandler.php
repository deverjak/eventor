<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Doctrine\DBAL\Connection;
use Eventor\Payments\Contract\MatchingResult;
use Eventor\Payments\Domain\Inbox\InboxMessageRepository;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransaction;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransactionRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The mirror of unmatched transactions (chapter 07, "Nespárované transakce"): a row exists only for a transaction
 * without allocation and with a known reason; it closes when Pairrot allocates or ignores the transaction.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApplyPairrotTransactionUpdateHandler
{
    private const array CLOSING = ['allocated' => 'allocated', 'ignored' => 'ignored', 'duplicate' => 'closed', 'rejected' => 'closed'];

    public function __construct(
        private InboxMessageRepository $inbox,
        private UnmatchedTransactionRepository $transactions,
        private Connection $connection,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ApplyPairrotTransactionUpdate $command): void
    {
        $organizer = $this->tenant->require();
        $message = $this->inbox->lock($command->inboxMessageId);
        if (null === $message) {
            return;
        }
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        if (!$message->isPending()) {
            $message->processed($organizer, $now);

            return;
        }

        $event = PairrotEvent::fromPayload($message->payload());
        $sequence = $event->int('sequence');
        $pairrotTxId = $event->string('transaction_id');
        $handling = $event->optionalString('handling_state') ?? 'unallocated';
        $matching = \is_array($event->data['matching'] ?? null) ? $event->data['matching'] : [];
        $result = \is_string($matching['result'] ?? null) ? $matching['result'] : '';
        $suggested = \is_string($matching['payment_request_id'] ?? null) ? $matching['payment_request_id'] : null;

        $existing = $this->transactions->lockByPairrotId($pairrotTxId);
        $closing = self::CLOSING[$handling] ?? ('matched' === $result ? 'allocated' : null);

        if (null !== $closing) {
            if (null !== $existing && $existing->isOpen()) {
                [$order, $request] = $this->requestOf($suggested);
                $existing->close($sequence, $closing, $order, $request, $now);
            }
        } elseif (null !== ($reason = MatchingResult::tryFrom($result)) && 'CZK' === ($event->data['currency'] ?? 'CZK')) {
            if (null === $existing) {
                $this->transactions->add(UnmatchedTransaction::receive(
                    $organizer,
                    $pairrotTxId,
                    Money::of($event->int('amount'), Currency::CZK),
                    $event->optionalString('variable_symbol'),
                    new \DateTimeImmutable(($event->optionalString('booked_on') ?? $now->format('Y-m-d')).' 00:00:00 UTC'),
                    $reason,
                    $suggested,
                    $handling,
                    $sequence,
                    $now,
                ));
            } elseif ($existing->isOpen()) {
                $existing->refresh($sequence, $reason, $suggested, $handling);
            }
        }

        $message->processed($organizer, $now);
    }

    /**
     * @return array{0: OrderId|null, 1: PaymentRequestId|null}
     */
    private function requestOf(?string $pairrotRequestId): array
    {
        if (null === $pairrotRequestId) {
            return [null, null];
        }

        /** @var array{id: string, order_id: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, order_id FROM payments.payment_request WHERE pairrot_id = :id AND organizer_id = :organizer',
            ['id' => $pairrotRequestId, 'organizer' => $this->tenant->require()->toString()],
        );

        return false === $row ? [null, null] : [OrderId::fromString($row['order_id']), PaymentRequestId::fromString($row['id'])];
    }
}
