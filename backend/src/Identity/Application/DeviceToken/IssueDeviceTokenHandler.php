<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\DeviceToken;

use Eventor\Identity\Domain\DeviceToken\DeviceToken;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class IssueDeviceTokenHandler
{
    public function __construct(
        private DeviceTokenRepository $tokens,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{id: string, token: string}
     */
    public function __invoke(IssueDeviceToken $command): array
    {
        $id = DeviceTokenId::generate();
        $secret = DeviceTokenFormat::newSecret();

        $this->tokens->add(DeviceToken::issue(
            $id,
            $command->organizerId,
            $command->checkInListId,
            $command->label,
            DeviceTokenFormat::hash($secret),
            $command->createdBy,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
            $command->expiresAt,
        ));

        return ['id' => $id->toString(), 'token' => DeviceTokenFormat::format($id, $secret)];
    }
}
