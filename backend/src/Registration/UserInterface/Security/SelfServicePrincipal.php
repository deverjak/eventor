<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Security;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated participant: bound to one order of one organizer by a self-service token. It lives for one request
 * only (the `self_service` firewall is stateless), so it is never serialised.
 */
final readonly class SelfServicePrincipal implements UserInterface
{
    public function __construct(
        public SelfServiceTokenId $tokenId,
        public OrganizerId $organizerId,
        public OrderId $orderId,
    ) {}

    public function getUserIdentifier(): string
    {
        return 'self-service:'.$this->tokenId->toString();
    }

    public function getRoles(): array
    {
        return ['ROLE_PARTICIPANT'];
    }
}
