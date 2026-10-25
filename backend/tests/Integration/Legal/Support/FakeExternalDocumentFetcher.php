<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Application\Port\ExternalDocumentFetcher;
use Eventor\Legal\Domain\Exception\InvalidDocument;

/**
 * Test double of the SSRF-safe downloader: serves canned documents, fails like the real one for unknown addresses.
 */
final class FakeExternalDocumentFetcher implements ExternalDocumentFetcher
{
    /** @var array<string, array{body: string, mime: string}> */
    private static array $documents = [];

    public static function serve(string $url, string $body, string $mime = 'text/html'): void
    {
        self::$documents[$url] = ['body' => $body, 'mime' => $mime];
    }

    public static function reset(): void
    {
        self::$documents = [];
    }

    public function fetch(string $url): array
    {
        return self::$documents[$url] ?? throw InvalidDocument::because('The linked document could not be downloaded.');
    }
}
