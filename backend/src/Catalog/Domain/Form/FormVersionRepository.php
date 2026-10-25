<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

use Eventor\Shared\Domain\Id\EventId;

interface FormVersionRepository
{
    /** The mutable draft of the event, if there is one. */
    public function draftOf(EventId $event): ?FormVersion;

    /** The highest published version, the one new registrations are validated against. */
    public function latestPublished(EventId $event): ?FormVersion;

    /** The highest number in use (draft or published); 0 when the event has no form yet. */
    public function highestNumber(EventId $event): int;

    public function add(FormVersion $version): void;
}
