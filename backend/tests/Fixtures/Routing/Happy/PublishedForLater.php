<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\Happy;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\NoSubscribers;

#[IntegrationEvent(name: 'fixture.later')]
#[NoSubscribers(reason: 'Nobody listens yet.')]
final readonly class PublishedForLater {}
