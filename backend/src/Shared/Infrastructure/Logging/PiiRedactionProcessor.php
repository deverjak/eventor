<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Logs never carry personal data or secrets (chapter 08): e-mail addresses, phone numbers, IBANs and every token
 * with a known prefix (`ss1_`, `dev1_`, `qr1.`, `prt_`, `pwhsec_`) are masked in the message and the context.
 */
#[AsMonologProcessor]
final class PiiRedactionProcessor
{
    private const array PATTERNS = [
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/' => '[email]',
        '/\b(?:ss1|dev1|prt|pwhsec)_[A-Za-z0-9_-]{6,}/' => '[token]',
        '/\bqr1\.[A-Za-z0-9_.-]{6,}/' => '[token]',
        '/\b[A-Z]{2}\d{2}(?:\s?\d{4}){4,7}\b/' => '[iban]',
        '/(?<!\d)\+?\d{3}[ ]?\d{3}[ ]?\d{3}[ ]?\d{3}(?!\d)/' => '[phone]',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $context */
        $context = $this->scrub($record->context);

        return $record->with(message: $this->scrubString($record->message), context: $context);
    }

    private function scrub(mixed $value): mixed
    {
        if (\is_string($value)) {
            return $this->scrubString($value);
        }
        if (\is_array($value)) {
            return array_map($this->scrub(...), $value);
        }

        return $value;
    }

    private function scrubString(string $value): string
    {
        return preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $value) ?? $value;
    }
}
