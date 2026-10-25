<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

/**
 * Typed view of a Pairrot event envelope as stored in the inbox (`id`, `type`, `created_at`, `tenant_id`, `data`).
 * Reading is lenient about extra fields and strict about the ones Eventor uses, so a changed contract fails loudly
 * instead of booking wrong money.
 */
final readonly class PairrotEvent
{
    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        public string $id,
        public string $type,
        public string $tenantId,
        public \DateTimeImmutable $createdAt,
        public array $data,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $data = $payload['data'] ?? [];
        $data = \is_array($data) ? $data : [];
        /** @var array<string, mixed> $data */

        return new self(
            self::requireString($payload, 'id'),
            self::requireString($payload, 'type'),
            \is_string($payload['tenant_id'] ?? null) ? $payload['tenant_id'] : '',
            new \DateTimeImmutable(\is_string($payload['created_at'] ?? null) ? $payload['created_at'] : '@0'),
            $data,
        );
    }

    public function string(string $key): string
    {
        return self::requireString($this->data, $key);
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;
        if (!\is_int($value)) {
            throw new \UnexpectedValueException(\sprintf('Pairrot event %s lacks the integer "%s".', $this->id, $key));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function object(string $key): array
    {
        $value = $this->data[$key] ?? null;
        if (!\is_array($value)) {
            throw new \UnexpectedValueException(\sprintf('Pairrot event %s lacks the object "%s".', $this->id, $key));
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function requireString(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            throw new \UnexpectedValueException(\sprintf('Pairrot event lacks the string "%s".', $key));
        }

        return $value;
    }
}
