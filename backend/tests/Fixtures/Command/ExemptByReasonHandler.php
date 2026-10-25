<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Command;

use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Fixture: documents the exemption.')]
final readonly class ExemptByReasonHandler
{
    public function __invoke(ExemptByReason $command): void {}
}
