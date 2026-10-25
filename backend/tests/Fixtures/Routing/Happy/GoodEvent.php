<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\Happy;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;

#[IntegrationEvent(name: 'fixture.good', version: 1)]
final readonly class GoodEvent
{
    public function __construct(public string $id) {}
}
