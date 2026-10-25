<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Port;

use Eventor\Legal\Domain\Document\CanonicalContent;

/**
 * Content-addressed storage of document bytes (ADR 012): the key derives from the SHA-256, identical content is
 * stored once, and a stored key is never overwritten with different bytes.
 */
interface DocumentContentStore
{
    /**
     * Stores the content under its key (a no-op when already present) and returns the key.
     */
    public function put(CanonicalContent $content): string;

    /**
     * @throws \RuntimeException when the key is unknown
     */
    public function read(string $storageKey): string;

    public function exists(string $storageKey): bool;
}
