<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Payments\Application\Port\GatewayAlerts;
use Eventor\Payments\Domain\Inbox\InboxMessageRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RejectInboxMessageHandler
{
    public function __construct(
        private InboxMessageRepository $inbox,
        private GatewayAlerts $alerts,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RejectInboxMessage $command): void
    {
        $message = $this->inbox->lock($command->inboxMessageId);
        if (null === $message) {
            return;
        }

        if ($message->isPending()) {
            $this->alerts->anomaly(null, 'message_rejected:'.$command->reason);
        }
        $message->rejected(null, $command->reason, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
