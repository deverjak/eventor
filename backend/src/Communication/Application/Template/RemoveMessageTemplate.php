<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Template;

/**
 * Drops the organizer's wording; the built-in text applies again.
 */
final readonly class RemoveMessageTemplate
{
    public function __construct(public string $templateId) {}
}
