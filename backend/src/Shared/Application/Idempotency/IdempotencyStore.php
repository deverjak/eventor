<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Idempotency;

/**
 * Persistence of `shared.idempotency_record` (chapter 08, "Idempotence"): same key + same request hash replays the
 * stored response, same key + different hash is a conflict.
 */
interface IdempotencyStore
{
    /**
     * Claims the key. Returns null when this caller must execute the command, or the stored response of an
     * earlier identical request.
     *
     * @throws IdempotencyKeyReused when the key was used with a different request
     * @throws RequestInProgress    when an identical request has not completed yet
     */
    public function begin(string $scope, string $key, string $requestHash): ?StoredResponse;

    /**
     * @param mixed $response JSON-serialisable command result (arrays, scalars, null, JsonSerializable)
     */
    public function complete(string $scope, string $key, mixed $response): void;

    /** The command failed: free the key so the client can retry. */
    public function abandon(string $scope, string $key): void;
}
