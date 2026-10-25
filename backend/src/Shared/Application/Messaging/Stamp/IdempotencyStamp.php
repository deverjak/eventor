<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Messaging\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Put on a command by the controller (`$bus->dispatch($command, [IdempotencyStamp::for(...)])`) to make it
 * replay-safe. `scope` separates callers (e.g. `public_order:<org>:<event>`), `requestHash` fingerprints the
 * canonical request body. Not sent over transports: only command.bus uses it.
 */
final readonly class IdempotencyStamp implements StampInterface
{
    public function __construct(
        public string $scope,
        public string $key,
        public string $requestHash,
    ) {}

    /**
     * @param string|array<array-key, mixed> $canonicalBody the request payload; arrays are hashed in a stable order
     */
    public static function for(string $scope, string $key, string|array $canonicalBody): self
    {
        if (\is_array($canonicalBody)) {
            $canonicalBody = self::canonicalJson($canonicalBody);
        }

        return new self($scope, $key, hash('sha256', $canonicalBody));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function canonicalJson(array $data): string
    {
        $sort = static function (mixed $value) use (&$sort): mixed {
            if (!\is_array($value)) {
                return $value;
            }
            $value = array_map($sort, $value);
            if (!array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return json_encode($sort($data), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
