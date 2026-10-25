<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish;

use Eventor\Catalog\Domain\Exception\EventNotPublishable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Runs every publication rule and reports all broken ones at once (422 with the list), so the UI can lead the
 * organizer to each fix.
 */
final readonly class EventPublishabilityChecker
{
    /**
     * @param iterable<PublishRule> $rules
     */
    public function __construct(
        #[AutowireIterator(PublishRule::TAG)]
        private iterable $rules,
    ) {}

    /**
     * @throws EventNotPublishable
     */
    public function assertPublishable(PublishCandidate $candidate): void
    {
        $violations = [];
        foreach ($this->rules as $rule) {
            array_push($violations, ...$rule->check($candidate));
        }
        if ([] !== $violations) {
            throw new EventNotPublishable($violations);
        }
    }
}
