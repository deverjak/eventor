<?php

declare(strict_types=1);

namespace Eventor\Content\Infrastructure\Storage;

use Eventor\Content\Application\Port\PhotoStorage;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[AsAlias(PhotoStorage::class)]
final readonly class FlysystemPhotoStorage implements PhotoStorage
{
    public function __construct(#[Target('content')] private FilesystemOperator $storage) {}

    public function put(string $key, string $bytes): void
    {
        $this->storage->write($key, $bytes);
    }

    public function read(string $key): string
    {
        return $this->storage->read($key);
    }
}
