<?php

declare(strict_types=1);

namespace Eventor\Shared\Application\Attention;

/**
 * Port to the Attention module's queue. Shared infrastructure (DeadLetterListener) raises items through it
 * without knowing the module; implementations must not throw for an existing dedupe key.
 */
interface AttentionRaiser
{
    public function raise(AttentionRequest $request): void;
}
