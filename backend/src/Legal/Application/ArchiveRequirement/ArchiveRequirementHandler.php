<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\ArchiveRequirement;

use Eventor\Legal\Domain\Exception\RequirementNotFound;
use Eventor\Legal\Domain\Requirement\LegalRequirementRepository;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ArchiveRequirementHandler
{
    public function __construct(
        private LegalRequirementRepository $requirements,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ArchiveRequirement $command): void
    {
        $id = LegalRequirementId::fromString($command->requirementId);
        $requirement = $this->requirements->find($id) ?? throw RequirementNotFound::forId($id);
        $requirement->archive(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
