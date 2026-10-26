<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UploadPhoto;

use Eventor\Content\Application\Port\PhotoStorage;
use Eventor\Content\Application\Port\Thumbnailer;
use Eventor\Content\Application\UpdateCollection\CollectionOfEvent;
use Eventor\Content\Contract\Exception\InvalidPhoto;
use Eventor\Content\Domain\Collection\CollectionRepository;
use Eventor\Content\Domain\Collection\Photo;
use Eventor\Shared\Domain\Id\PhotoId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * POST-01: stores the original, then the photo row; `content.photo_uploaded` makes the thumbnail asynchronously.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class UploadPhotoHandler
{
    public const int MAX_BYTES = 15 * 1024 * 1024;

    private const array EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        private CollectionRepository $collections,
        private CollectionOfEvent $collectionOfEvent,
        private PhotoStorage $storage,
        private Thumbnailer $images,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{photoId: string}
     */
    public function __invoke(UploadPhoto $command): array
    {
        $collection = $this->collectionOfEvent->get($command->eventId, $command->collectionId);
        $collection->assertAcceptsPhotos();
        if (\strlen($command->bytes) > self::MAX_BYTES) {
            throw InvalidPhoto::because('The photo is larger than 15 MB.');
        }
        $contentType = $this->images->detect($command->bytes);

        $id = PhotoId::generate();
        $key = \sprintf('%s/%s/%s.%s', $collection->organizerId()->toString(), $collection->id()->toString(), $id->toString(), self::EXTENSIONS[$contentType] ?? 'bin');
        $this->storage->put($key, $command->bytes);

        $position = $command->position ?? $this->collections->nextPhotoPosition($collection->id());
        $photo = Photo::add($id, $collection, $key, $contentType, $command->description, $command->author, $command->eventPart, $position, \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->collections->addPhoto($photo);

        return ['photoId' => $id->toString()];
    }
}
