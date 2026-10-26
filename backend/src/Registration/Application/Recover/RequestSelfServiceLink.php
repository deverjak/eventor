<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Recover;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * REG-11: the orderer asks for the self-service link again by e-mail. Nothing in the answer tells whether the address is
 * known; each matching order gets `registration.self_service_link_requested` and Communication sends the link.
 */
final readonly class RequestSelfServiceLink
{
    public function __construct(
        public OrganizerId $organizerId,
        public string $email,
        public ?EventId $eventId = null,
    ) {}
}
