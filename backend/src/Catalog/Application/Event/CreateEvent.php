<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Shared\Domain\Id\OrganizerId;

final readonly class CreateEvent
{
    public function __construct(
        public OrganizerId $organizerId,
        public CreateEventInput $input,
    ) {}
}
