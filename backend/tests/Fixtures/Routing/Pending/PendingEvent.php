<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\Pending;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

#[IntegrationEvent(name: 'fixture.pending')]
#[PendingSubscribers('payments', 'Communication')]
final readonly class PendingEvent {}
