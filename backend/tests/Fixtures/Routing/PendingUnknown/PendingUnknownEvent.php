<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\PendingUnknown;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

#[IntegrationEvent(name: 'fixture.pending_unknown')]
#[PendingSubscribers('payments', 'paymnets')]
final readonly class PendingUnknownEvent {}
