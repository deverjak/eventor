<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Sending;

final readonly class SendOutcome
{
    private function __construct(
        public bool $sent,
        public ?string $messageId,
        public ?string $suppressedBecause,
    ) {}

    public static function sent(?string $messageId): self
    {
        return new self(true, $messageId, null);
    }

    /**
     * @param string $reason stable code: `consent_withdrawn`, `suppressed`
     */
    public static function suppressed(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
