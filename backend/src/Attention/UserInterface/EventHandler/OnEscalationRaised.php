<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\CheckIn\Contract\Event\EscalationRaised;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The check-in operator cannot resolve a situation at the desk (CHK-09); stored as missing_confirmation.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnEscalationRaised
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(EscalationRaised $event): void
    {
        $this->attention->open(AttentionType::MissingConfirmation, $event->organizerId, 'participation', $event->participationId->toString(), [
            'source' => 'check_in_escalation',
            'situation' => $event->situation->value,
            'listId' => $event->listId->toString(),
        ]);
    }
}
