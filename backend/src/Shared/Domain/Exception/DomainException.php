<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Exception;

/**
 * Base of every business-rule violation. Subclasses carry `#[AsProblem(code, status)]`; the HTTP layer turns
 * them into problem details, `context()` and `nextSteps()` feed the optional `context` / `nextSteps` members.
 */
abstract class DomainException extends \RuntimeException
{
    /**
     * Machine-readable details safe to expose to the client (no personal data).
     *
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return [];
    }

    /**
     * Suggested follow-up actions for the client (`{action, href?}`), e.g. joining a waitlist.
     *
     * @return list<array{action: string, href?: string}>
     */
    public function nextSteps(): array
    {
        return [];
    }
}
