<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port;

/**
 * Writes the webhook inbox. A single `INSERT ... ON CONFLICT (provider, message_id) DO NOTHING`: a duplicate delivery
 * (S03) or a reconcile of an already known event ends here without an error and without a second effect.
 */
interface InboxStore
{
    public const string PROVIDER = 'pairrot';

    /**
     * @param array<string, mixed> $payload
     *
     * @return string|null the id of the new row, null when the message was already known
     */
    public function receive(string $messageId, string $type, array $payload, string $status = 'pending'): ?string;

    /**
     * What the processing needs to route a message: type, tenant reference and request reference.
     *
     * @return array{type: string, status: string, payload: array<string, mixed>}|null
     */
    public function peek(string $inboxId): ?array;

    /**
     * Organizer of a payment request by the id Pairrot knows, or null.
     */
    public function organizerOfRequest(string $pairrotRequestId): ?\Eventor\Shared\Domain\Id\OrganizerId;
}
