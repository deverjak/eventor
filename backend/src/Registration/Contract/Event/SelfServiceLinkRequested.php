<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;

/**
 * The orderer asked for a new self-service link (REG-11). The secret never travels on the bus: Communication builds the link with `SelfServiceLinks::urlFor()`.
 *
 * P0. Planned subscribers: communication.
 * No personal data: ids, amounts, states and times only (chapter 06, catalogue).
 */
#[IntegrationEvent(name: 'registration.self_service_link_requested', version: 1)]
final readonly class SelfServiceLinkRequested
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public SelfServiceTokenId $tokenId,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
