<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Suppression;

use Eventor\Communication\Domain\Exception\SuppressionNotFound;
use Eventor\Communication\Domain\Suppression\SuppressionId;
use Eventor\Communication\Domain\Suppression\SuppressionRepository;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('The removed row leaves the unit of work, so its event could not be collected (and Audit has no write Contract); lifting a suppression is not audited.')]
final readonly class LiftSuppressionHandler
{
    public function __construct(private SuppressionRepository $suppressions) {}

    public function __invoke(LiftSuppression $command): void
    {
        $suppression = $this->suppressions->find(SuppressionId::fromString($command->suppressionId)) ?? throw SuppressionNotFound::forId($command->suppressionId);
        $this->suppressions->remove($suppression);
    }
}
