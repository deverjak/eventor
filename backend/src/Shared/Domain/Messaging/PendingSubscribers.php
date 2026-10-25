<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Messaging;

/**
 * Scaffold for contract-first development: the event is part of the catalogue and its subscribers are planned but
 * not written yet. IntegrationEventRoutingPass accepts an event without a subscriber only when this attribute lists
 * the modules that will subscribe, and it fails the build as soon as a listed module really has a handler
 * (`#[AsMessageHandler(fromTransport: '<module>_events')]`), so the list shrinks while the modules arrive and
 * disappears when the last subscriber is written. W4 turns the remaining list into a failing test
 * (EVENTOR_STRICT_SUBSCRIBERS=1).
 *
 *     #[IntegrationEvent(name: 'registration.order_placed', version: 1)]
 *     #[PendingSubscribers('payments', 'communication', 'reporting')]
 *
 * Module names are the lower-case transport prefixes (`payments` for `payments_events`, `checkin` for CheckIn).
 * An event that truly has nobody to inform uses #[NoSubscribers] instead; the two attributes exclude each other.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class PendingSubscribers
{
    /** @var non-empty-list<string> */
    public array $modules;

    public function __construct(string $module, string ...$more)
    {
        $modules = array_values(array_unique(array_map(strtolower(...), [$module, ...$more])));
        $this->modules = $modules;
    }
}
