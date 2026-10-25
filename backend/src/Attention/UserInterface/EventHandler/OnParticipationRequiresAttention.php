<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Registration\Contract\Event\ParticipationRequiresAttention;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A participation needs a human decision; shares its item with LatePaymentReceived (same type and subject).
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnParticipationRequiresAttention
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(ParticipationRequiresAttention $event): void
    {
        $this->attention->openForOrder(AttentionType::LatePayment, $event->organizerId, $event->orderId, 'participation', $event->participationId->toString(), ['cause' => $event->cause->value]);
    }
}
