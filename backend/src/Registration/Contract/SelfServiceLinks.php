<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * The participant-facing self-service URL of an order (REG-08). The token secret is derived (for example an HMAC of
 * the token id with the application secret; only its SHA-256 is stored for revocation), so Communication can build
 * the link whenever it sends a message without the secret ever travelling on the bus.
 */
interface SelfServiceLinks
{
    /**
     * @throws OrderNotFound
     */
    public function urlFor(OrganizerId $organizer, OrderId $order): string;
}
