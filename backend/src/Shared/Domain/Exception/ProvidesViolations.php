<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Exception;

/**
 * A domain exception that lists the offending fields (`form_invalid`, `missing_confirmation`, `event_not_publishable`).
 * ProblemFactory copies the list into the `violations` member of the problem, like it does for request validation,
 * so the UI can mark every field instead of showing one message.
 */
interface ProvidesViolations
{
    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public function violations(): array;
}
