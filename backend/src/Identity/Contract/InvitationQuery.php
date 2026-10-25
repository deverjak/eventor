<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * What Communication needs to send a team invitation (ORG-03). The accept link carries a secret that is not stored,
 * so it is built here at delivery time and never travels on the bus.
 */
interface InvitationQuery
{
    /**
     * @throws InvitationNotFound
     */
    public function forDelivery(OrganizerId $organizer, InvitationId $invitation): InvitationDeliveryView;
}
