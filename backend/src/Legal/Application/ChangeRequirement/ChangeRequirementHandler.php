<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\ChangeRequirement;

use Eventor\Legal\Domain\Exception\RequirementNotFound;
use Eventor\Legal\Domain\Requirement\LegalRequirementRepository;
use Eventor\Legal\Domain\Requirement\RequirementPolicy;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\VariantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ChangeRequirementHandler
{
    public function __construct(private LegalRequirementRepository $requirements) {}

    public function __invoke(ChangeRequirement $command): void
    {
        $id = LegalRequirementId::fromString($command->requirementId);
        $requirement = $this->requirements->find($id) ?? throw RequirementNotFound::forId($id);

        $requirement->change(
            $command->purpose,
            $command->scopeText,
            $command->validity,
            $command->confirmer,
            $command->revocable ?? RequirementPolicy::isVoluntary($requirement->type()),
            $command->mandatory,
            $command->preChecked,
            null === $command->activityId ? null : ActivityId::fromString($command->activityId),
            null === $command->variantId ? null : VariantId::fromString($command->variantId),
        );
    }
}
