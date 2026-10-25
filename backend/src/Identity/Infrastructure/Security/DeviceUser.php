<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Contract\DevicePrincipal;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated principal of a check-in device: bound to one organizer and optionally to one check-in list.
 * It lives for one request only (the `check_in` firewall is stateless), so it is never serialised.
 */
final readonly class DeviceUser implements UserInterface, DevicePrincipal
{
    public function __construct(
        private string $deviceTokenId,
        private OrganizerId $organizerId,
        private ?CheckInListId $checkInListId,
        private string $label,
    ) {}

    public function deviceTokenId(): string
    {
        return $this->deviceTokenId;
    }

    public function organizerId(): OrganizerId
    {
        return $this->organizerId;
    }

    public function checkInListId(): ?CheckInListId
    {
        return $this->checkInListId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function getUserIdentifier(): string
    {
        return 'device:'.$this->deviceTokenId;
    }

    public function getRoles(): array
    {
        return ['ROLE_DEVICE'];
    }
}
