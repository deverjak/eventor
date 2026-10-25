<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query;

use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;

/**
 * A published form version as read from the database.
 */
final readonly class StoredFormVersion
{
    public function __construct(
        public FormVersionId $id,
        public EventId $eventId,
        public int $number,
        public \DateTimeImmutable $publishedAt,
        public string $schemaSha256,
        public FormSchema $schema,
    ) {}
}
