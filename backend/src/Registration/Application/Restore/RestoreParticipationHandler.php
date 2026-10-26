<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Restore;

use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\Contract\RestoredBy;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The restore action of the exception queue and of the participation list (Attention shows "restore_participation" for a
 * late payment).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class RestoreParticipationHandler
{
    public function __construct(
        private ParticipationRepository $participations,
        private OrderRepository $orders,
        private ParticipationRestorer $restorer,
    ) {}

    public function __invoke(RestoreParticipation $command): void
    {
        $reason = trim($command->reason ?? '');
        if (RestoredBy::User === $command->by && '' === $reason) {
            throw InvalidOrderRequest::at('reason', 'reason_required', 'A reason is required to restore a participation.');
        }

        $participation = $this->participations->get($command->participationId);
        $this->restorer->restore($this->orders->get($participation->orderId()), $participation, $command->by, '' === $reason ? null : $reason);
    }
}
