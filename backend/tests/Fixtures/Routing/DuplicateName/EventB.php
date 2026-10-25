<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\DuplicateName;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\NoSubscribers;

#[IntegrationEvent(name: 'fixture.same_name')]
#[NoSubscribers]
final readonly class EventB {}
