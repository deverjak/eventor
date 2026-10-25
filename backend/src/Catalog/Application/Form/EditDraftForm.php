<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Shared\Domain\Id\EventId;

/**
 * Replaces the draft of the registration form (FRM-01..06). `schema` is the raw JSON object `{"fields": [...]}`; the
 * domain parses and validates it and reports every problem with its path.
 */
final readonly class EditDraftForm
{
    /**
     * @param array<array-key, mixed> $schema
     */
    public function __construct(
        public EventId $eventId,
        public array $schema,
    ) {}
}
