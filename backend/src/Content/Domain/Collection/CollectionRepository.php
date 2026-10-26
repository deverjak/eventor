<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Collection;

use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\PhotoId;

interface CollectionRepository
{
    public function find(CollectionId $id): ?Collection;

    public function add(Collection $collection): void;

    public function findPhoto(PhotoId $id): ?Photo;

    public function addPhoto(Photo $photo): void;

    public function nextPhotoPosition(CollectionId $collection): int;
}
