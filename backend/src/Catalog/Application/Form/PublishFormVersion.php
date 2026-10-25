<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Shared\Domain\Id\EventId;

final readonly class PublishFormVersion
{
    public function __construct(public EventId $eventId) {}
}
