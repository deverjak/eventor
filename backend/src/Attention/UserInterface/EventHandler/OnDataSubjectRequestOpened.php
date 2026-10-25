<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Privacy\Contract\Event\DataSubjectRequestOpened;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A data subject request was opened (PRV-03) and has a legal deadline; stored as cancellation_request (the closest existing type).
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnDataSubjectRequestOpened
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(DataSubjectRequestOpened $event): void
    {
        $this->attention->open(AttentionType::CancellationRequest, $event->organizerId, 'data_subject_request', $event->requestId->toString(), [
            'source' => 'data_subject_request',
            'requestType' => $event->type->value,
            'dueAt' => $event->dueAt->format(\DATE_ATOM),
        ]);
    }
}
