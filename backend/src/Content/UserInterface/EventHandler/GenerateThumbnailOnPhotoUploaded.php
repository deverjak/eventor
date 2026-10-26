<?php

declare(strict_types=1);

namespace Eventor\Content\UserInterface\EventHandler;

use Eventor\Content\Application\GenerateThumbnail\GenerateThumbnail;
use Eventor\Content\Contract\Event\PhotoUploaded;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `content.photo_uploaded` -> a thumbnail, in the worker and outside the upload request (POST-01, chapter 06).
 */
#[AsMessageHandler(fromTransport: 'content_events')]
final readonly class GenerateThumbnailOnPhotoUploaded
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(PhotoUploaded $event): void
    {
        $this->commands->dispatch(new GenerateThumbnail($event->photoId->toString()), TenantStamp::of($event->organizerId));
    }
}
