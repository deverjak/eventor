<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\PendingAndNone;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\NoSubscribers;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;

#[IntegrationEvent(name: 'fixture.pending_and_none')]
#[NoSubscribers(reason: 'contradiction')]
#[PendingSubscribers('payments')]
final readonly class PendingAndNoSubscribersEvent {}
