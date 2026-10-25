<?php

declare(strict_types=1);

namespace Eventor\Legal\Infrastructure\Storage;

use Eventor\Legal\Application\Port\DocumentContentStore;
use Eventor\Legal\Domain\Document\CanonicalContent;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Writes document bytes to the `legal` storage (MinIO; in-memory in tests) under `sha256/<ab>/<hash>.<ext>`.
 * Identical content is stored once; because the key is the hash of the bytes, re-hashing an object proves it was not
 * tampered with.
 */
#[AsAlias(DocumentContentStore::class)]
final readonly class ContentAddressedStore implements DocumentContentStore
{
    public function __construct(
        #[Target('legal')]
        private FilesystemOperator $storage,
    ) {}

    public function put(CanonicalContent $content): string
    {
        $key = $content->storageKey();
        if (!$this->storage->fileExists($key)) {
            $this->storage->write($key, $content->bytes);
        }

        return $key;
    }

    public function read(string $storageKey): string
    {
        return $this->storage->read($storageKey);
    }

    public function exists(string $storageKey): bool
    {
        return $this->storage->fileExists($storageKey);
    }
}
