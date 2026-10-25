<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Legal\Contract\Event\ConsentWithdrawn;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A revocable consent was withdrawn (S16); for likeness the handler also offers the photo review.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnConsentWithdrawn
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(ConsentWithdrawn $event): void
    {
        $this->attention->openForOrder(AttentionType::ConsentWithdrawn, $event->organizerId, $event->orderId, 'confirmation', $event->confirmationId->toString(), [
            'requirementType' => $event->requirementType->value,
            'participationId' => $event->participationId?->toString(),
        ]);
    }
}
