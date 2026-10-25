<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\EventHandler;

use Eventor\Attention\Application\Open\AttentionIntake;
use Eventor\Content\Contract\Event\CollectionPublished;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A collection became visible (POST-06): ask the organizer to check the likeness consents. Stored as missing_confirmation with the collection as subject.
 */
#[AsMessageHandler(fromTransport: 'attention_events')]
final readonly class OnCollectionPublished
{
    public function __construct(private AttentionIntake $attention) {}

    public function __invoke(CollectionPublished $event): void
    {
        $this->attention->open(AttentionType::MissingConfirmation, $event->organizerId, 'collection', $event->collectionId->toString(), [
            'source' => 'likeness_review',
            'visibility' => $event->visibility->value,
        ], $event->eventId);
    }
}
