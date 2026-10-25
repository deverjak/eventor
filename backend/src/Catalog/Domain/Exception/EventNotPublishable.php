<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;

/**
 * The publication rules (`EventPublishabilityChecker`, chapter 04 path 1) are not met. Every broken rule is listed,
 * so the UI can lead the organizer to the fix.
 */
#[AsProblem(code: 'event_not_publishable', status: 422)]
final class EventNotPublishable extends DomainException implements ProvidesViolations
{
    /**
     * @param list<array{path: string, message: string, code: string}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct(\sprintf('The event cannot be published yet: %d rule(s) are not met.', \count($violations)));
    }

    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['violationCount' => \count($this->violations)];
    }
}
