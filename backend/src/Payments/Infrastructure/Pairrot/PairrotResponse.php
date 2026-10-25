<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot;

/**
 * A decoded Pairrot answer: status, JSON body and headers. Errors use `{"error": {"code": "...", "message": "..."}}`.
 */
final readonly class PairrotResponse
{
    /**
     * @param array<string, mixed>        $data
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $status,
        public array $data,
        public array $headers = [],
    ) {}

    public function errorCode(): string
    {
        $error = $this->data['error'] ?? null;
        if (\is_array($error) && \is_string($error['code'] ?? null)) {
            return $error['code'];
        }

        return 'http_'.$this->status;
    }
}
