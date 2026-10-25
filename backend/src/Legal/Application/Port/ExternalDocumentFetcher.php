<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\Port;

use Eventor\Legal\Domain\Exception\InvalidDocument;

/**
 * Downloads the body of an external legal document at publication time (LEG-01). Implementations protect against
 * SSRF (https only, no private addresses, few redirects) and limit size and duration.
 */
interface ExternalDocumentFetcher
{
    /**
     * @return array{body: string, mime: string}
     *
     * @throws InvalidDocument when the document cannot be fetched
     */
    public function fetch(string $url): array;
}
