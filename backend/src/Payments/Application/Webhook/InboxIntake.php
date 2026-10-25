<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Payments\Application\Port\InboxStore;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The single entrance of Pairrot messages into the inbox, for the webhook and for the reconcile job alike (chapter 07:
 * "peníze z Pairrotu přicházejí do ledgeru jedinou cestou"). Dedup is the unique `(provider, message_id)`; a new message
 * is queued on `pairrot_inbox` in the same transaction.
 */
final readonly class InboxIntake
{
    /** Event types Eventor applies; everything else is stored as already processed (an audit trail, no effect). */
    public const array HANDLED_TYPES = [
        'payment_request.updated',
        'payment_request.snapshot',
        'transaction.recorded',
        'transaction.updated',
        'review_case.opened',
        'review_case.updated',
        'inbound_address.paused',
        'inbound_address.resumed',
    ];

    public function __construct(
        private InboxStore $inbox,
        #[Target('event.bus')]
        private MessageBusInterface $eventBus,
    ) {}

    /**
     * @param array<string, mixed> $payload a Pairrot event envelope
     *
     * @return 'accepted'|'duplicate'
     */
    public function accept(array $payload): string
    {
        if (!\is_string($payload['id'] ?? null) || '' === $payload['id'] || !\is_string($payload['type'] ?? null)) {
            throw InvalidWebhookPayload::create('missing id or type');
        }

        $type = $payload['type'];
        $handled = \in_array($type, self::HANDLED_TYPES, true);
        $id = $this->inbox->receive($payload['id'], $type, $payload, $handled ? 'pending' : 'processed');
        if (null === $id) {
            return 'duplicate';
        }
        if ($handled) {
            $this->eventBus->dispatch(new ProcessInboxMessage($id));
        }

        return 'accepted';
    }
}
