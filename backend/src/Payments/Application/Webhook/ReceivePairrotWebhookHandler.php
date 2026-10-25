<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Chapter 07, "Příjem webhooků": `INSERT INTO inbox_message ... ON CONFLICT DO NOTHING`, then queue the processing.
 * Both happen in the transaction of the command (the `pairrot_inbox` transport is the outbox), so a stored message is
 * never left unprocessed and a duplicate delivery (S03) ends at the unique key. The answer is `accepted` or `duplicate`;
 * the endpoint answers 200 for both.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Technical write of the webhook inbox; the effects of the message record their own events when it is applied.')]
final readonly class ReceivePairrotWebhookHandler
{
    public function __construct(private InboxIntake $intake) {}

    /**
     * @return 'accepted'|'duplicate'
     */
    public function __invoke(ReceivePairrotWebhook $command): string
    {
        try {
            $payload = json_decode($command->body, true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw InvalidWebhookPayload::create('not JSON');
        }
        if (!\is_array($payload)) {
            throw InvalidWebhookPayload::create('not an object');
        }
        /** @var array<string, mixed> $payload */

        return $this->intake->accept($payload);
    }
}
