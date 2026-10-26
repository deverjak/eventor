<?php

declare(strict_types=1);

namespace Eventor\Content\Application\GenerateThumbnail;

use Eventor\Content\Application\Port\PhotoStorage;
use Eventor\Content\Application\Port\Thumbnailer;
use Eventor\Content\Contract\Exception\PhotoNotFound;
use Eventor\Content\Domain\Collection\CollectionRepository;
use Eventor\Shared\Domain\Id\PhotoId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs in the worker that consumes `content.photo_uploaded` (POST-01 thumbnails). Idempotent: a redelivery writes the
 * same derivative under the same key.
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A thumbnail is a technical derivative of a photo already recorded by PhotoAdded.')]
final readonly class GenerateThumbnailHandler
{
    public const int SIZE = 480;

    public function __construct(
        private CollectionRepository $collections,
        private PhotoStorage $storage,
        private Thumbnailer $images,
    ) {}

    public function __invoke(GenerateThumbnail $command): void
    {
        $id = PhotoId::fromString($command->photoId);
        $photo = $this->collections->findPhoto($id) ?? throw PhotoNotFound::forId($id);

        $key = preg_replace('/\.[a-z]+$/', '', $photo->storageKey()).'-thumb.jpg';
        $this->storage->put($key, $this->images->thumbnail($this->storage->read($photo->storageKey()), self::SIZE));
        $photo->attachThumbnail($key);
    }
}
