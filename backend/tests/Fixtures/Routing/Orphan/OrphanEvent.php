<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\Orphan;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;

#[IntegrationEvent(name: 'fixture.orphan')]
final readonly class OrphanEvent {}
