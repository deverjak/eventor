<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\EventHandler;

use Eventor\Payments\Application\Port\InboxStore;
use Eventor\Payments\Application\Port\PairrotAccounts;
use Eventor\Payments\Application\Webhook\ApplyPairrotNotice;
use Eventor\Payments\Application\Webhook\ApplyPairrotPaymentUpdate;
use Eventor\Payments\Application\Webhook\ApplyPairrotTransactionUpdate;
use Eventor\Payments\Application\Webhook\PairrotEvent;
use Eventor\Payments\Application\Webhook\ProcessInboxMessage;
use Eventor\Payments\Application\Webhook\RejectInboxMessage;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Worker side of the inbox (`pairrot_inbox` transport): finds the organizer a verified Pairrot message belongs to and
 * applies it in that tenant, in its own transaction. A message of a request or tenant Eventor does not know is rejected
 * (and surfaced), never dropped silently (Pairrot's receiver contract, step 6).
 */
#[AsMessageHandler(fromTransport: 'pairrot_inbox')]
final readonly class ProcessInboxMessageSubscriber
{
    public function __construct(
        private InboxStore $inbox,
        private PairrotAccounts $accounts,
        private CommandBus $commands,
    ) {}

    public function __invoke(ProcessInboxMessage $message): void
    {
        $row = $this->inbox->peek($message->inboxMessageId);
        if (null === $row || 'pending' !== $row['status']) {
            return; // applied by an earlier delivery of this queue message
        }

        $event = PairrotEvent::fromPayload($row['payload']);
        $organizer = match ($event->type) {
            'payment_request.updated', 'payment_request.snapshot' => $this->inbox->organizerOfRequest($event->string('payment_request_id')),
            default => $this->accounts->findBySubtenant($event->tenantId)?->organizerId,
        };
        if (!$organizer instanceof OrganizerId) {
            $this->commands->dispatch(new RejectInboxMessage($message->inboxMessageId, 'unknown_tenant_or_request'));

            return;
        }

        $command = match ($event->type) {
            'payment_request.updated', 'payment_request.snapshot' => new ApplyPairrotPaymentUpdate($message->inboxMessageId),
            'transaction.recorded', 'transaction.updated' => new ApplyPairrotTransactionUpdate($message->inboxMessageId),
            default => new ApplyPairrotNotice($message->inboxMessageId),
        };
        $this->commands->dispatch($command, TenantStamp::of($organizer));
    }
}
