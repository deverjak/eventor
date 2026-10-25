<?php

declare(strict_types=1);

namespace Eventor\Tests\Api\Support;

/**
 * A response of the running API as the black-box tests see it: status, headers and body, nothing else.
 */
final readonly class ApiResponse
{
    /**
     * @param array<string, list<string>> $headers lower-case header names
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public function headerLines(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            throw new \UnexpectedValueException('The response body is not a JSON object/array: '.$this->body);
        }

        return $decoded;
    }
}
