<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\DeviceToken;

use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenNotFound;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RevokeDeviceTokenHandler
{
    public function __construct(
        private DeviceTokenRepository $tokens,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RevokeDeviceToken $command): void
    {
        $id = DeviceTokenId::isValid($command->deviceTokenId) ? DeviceTokenId::fromString($command->deviceTokenId) : throw DeviceTokenNotFound::forRaw($command->deviceTokenId);
        $token = $this->tokens->find($id, $command->organizerId) ?? throw DeviceTokenNotFound::forId($id);
        if ($token->isRevoked()) {
            throw DeviceTokenNotFound::forId($id); // nothing to revoke: it is gone for the caller
        }
        $token->revoke(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
