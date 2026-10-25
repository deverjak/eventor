<?php

declare(strict_types=1);

namespace Eventor\Legal\Domain\Document;

use Eventor\Legal\Domain\Exception\InvalidDocument;

/**
 * The canonical bytes of a version and their SHA-256 (ADR 012): text is UTF-8 in Unicode normalisation form NFC with
 * LF line ends, a PDF is stored as uploaded, an external link as the fetched body. The hash is the identity of the
 * content: the same bytes always give the same storage key.
 */
final readonly class CanonicalContent
{
    public const int MAX_TEXT_BYTES = 512 * 1024;
    public const int MAX_BINARY_BYTES = 5 * 1024 * 1024;

    private function __construct(
        public SourceKind $kind,
        public string $bytes,
        public string $mime,
        public string $extension,
        public string $sha256,
        public ?string $sourceUrl = null,
        public ?\DateTimeImmutable $fetchedAt = null,
    ) {}

    public static function text(string $text): self
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw InvalidDocument::because('The text must be valid UTF-8.');
        }
        $text = strtr($text, ["\r\n" => "\n", "\r" => "\n"]);
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            $text = \is_string($normalized) ? $normalized : $text;
        }
        if ('' === trim($text)) {
            throw InvalidDocument::because('The text of a legal document cannot be empty.');
        }
        if (\strlen($text) > self::MAX_TEXT_BYTES) {
            throw InvalidDocument::because('The text is too long (limit 512 kB).');
        }

        return new self(SourceKind::Text, $text, 'text/markdown; charset=utf-8', 'md', hash('sha256', $text));
    }

    public static function pdf(string $bytes): self
    {
        self::assertPdf($bytes);

        return new self(SourceKind::Pdf, $bytes, 'application/pdf', 'pdf', hash('sha256', $bytes));
    }

    /**
     * The immutable copy of an external link taken at publication time (LEG-01).
     */
    public static function externalSnapshot(string $url, string $body, string $mime, \DateTimeImmutable $fetchedAt): self
    {
        $mime = strtolower(trim(explode(';', $mime)[0]));
        if ('application/pdf' === $mime) {
            self::assertPdf($body);

            return new self(SourceKind::ExternalSnapshot, $body, 'application/pdf', 'pdf', hash('sha256', $body), $url, $fetchedAt);
        }
        if (!\in_array($mime, ['text/html', 'text/plain', 'application/xhtml+xml'], true)) {
            throw InvalidDocument::because(\sprintf('The linked document has an unsupported type "%s" (HTML, plain text or PDF expected).', $mime));
        }
        if ('' === trim($body) || \strlen($body) > self::MAX_BINARY_BYTES) {
            throw InvalidDocument::because('The linked document is empty or larger than 5 MB.');
        }
        $plain = 'text/plain' === $mime;

        return new self(SourceKind::ExternalSnapshot, $body, $plain ? 'text/plain; charset=utf-8' : 'text/html; charset=utf-8', $plain ? 'txt' : 'html', hash('sha256', $body), $url, $fetchedAt);
    }

    /**
     * Storage key: sha256/<first two hex digits>/<hash>.<ext>.
     */
    public function storageKey(): string
    {
        return \sprintf('sha256/%s/%s.%s', substr($this->sha256, 0, 2), $this->sha256, $this->extension);
    }

    private static function assertPdf(string $bytes): void
    {
        if (!str_starts_with($bytes, '%PDF-')) {
            throw InvalidDocument::because('The file is not a PDF.');
        }
        if (\strlen($bytes) > self::MAX_BINARY_BYTES) {
            throw InvalidDocument::because('The PDF is larger than 5 MB.');
        }
    }
}
