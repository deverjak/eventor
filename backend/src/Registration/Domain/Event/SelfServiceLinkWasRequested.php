<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Event;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;

/**
 * Domain event: the orderer asked for the self-service link again (REG-11). Published as
 * `registration.self_service_link_requested`; the secret never travels, Communication derives the URL.
 */
final readonly class SelfServiceLinkWasRequested
{
    public function __construct(
        public OrganizerId $organizerId,
        public OrderId $orderId,
        public SelfServiceTokenId $tokenId,
    ) {}
}
