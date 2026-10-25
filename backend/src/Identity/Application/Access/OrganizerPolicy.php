<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Access;

/**
 * Organizer-configurable exceptions to the permission matrix (`organizer_policy`, chapter 03), as the voters read
 * them. Loaded by `OrganizerPolicyProvider`; an organizer without a policy row gets the defaults (every flag off).
 */
final readonly class OrganizerPolicy
{
    public function __construct(
        public bool $checkInMayRecordCash = false,
        public bool $eventManagerMayResolvePayments = false,
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    public function isEnabled(string $flag): bool
    {
        return match ($flag) {
            'checkInMayRecordCash' => $this->checkInMayRecordCash,
            'eventManagerMayResolvePayments' => $this->eventManagerMayResolvePayments,
            default => false,
        };
    }
}
