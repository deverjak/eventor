<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;

/**
 * Read model of one immutable form version, for rendering and for export headers.
 */
final readonly class FormSchemaView
{
    /**
     * @param list<FormFieldView> $fields
     */
    public function __construct(
        public FormVersionId $formVersionId,
        public EventId $eventId,
        public int $version,
        public \DateTimeImmutable $publishedAt,
        public string $schemaSha256,
        public array $fields,
    ) {}
}
