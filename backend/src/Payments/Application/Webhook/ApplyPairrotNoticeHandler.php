<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Payments\Application\Port\GatewayAlerts;
use Eventor\Payments\Domain\Inbox\InboxMessageRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ApplyPairrotNoticeHandler
{
    public function __construct(
        private InboxMessageRepository $inbox,
        private GatewayAlerts $alerts,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ApplyPairrotNotice $command): void
    {
        $organizer = $this->tenant->require();
        $message = $this->inbox->lock($command->inboxMessageId);
        if (null === $message) {
            return;
        }

        if ($message->isPending()) {
            $event = PairrotEvent::fromPayload($message->payload());
            if ('review_case.opened' === $event->type) {
                // While a review case is open the transaction cannot be allocated: finance resolves it in Pairrot.
                $this->alerts->anomaly($organizer, 'pairrot_review_case_opened:'.$event->string('review_case_id'));
            } elseif ('inbound_address.paused' === $event->type) {
                $this->alerts->anomaly($organizer, 'inbound_address_paused');
            }
        }

        $message->processed($organizer, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
