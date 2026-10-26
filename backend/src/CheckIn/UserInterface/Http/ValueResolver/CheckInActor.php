<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http\ValueResolver;

use Eventor\Shared\Domain\Id\CheckInListId;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Who stands at the desk: a signed-in team member (`accountId`, never an e-mail) or a device token (`deviceLabel`, and
 * the one list the token is bound to, if any).
 */
final readonly class CheckInActor
{
    public function __construct(
        public ?string $accountId,
        public ?string $deviceLabel,
        public ?CheckInListId $deviceList,
    ) {}

    public function isDevice(): bool
    {
        return null !== $this->deviceLabel;
    }

    /**
     * A device token bound to one list works on that list only (ADR 006).
     */
    public function assertMayUse(CheckInListId $list): void
    {
        if (null !== $this->deviceList && !$this->deviceList->equals($list)) {
            throw new AccessDeniedException('This device token is bound to another check-in list.');
        }
    }
}
