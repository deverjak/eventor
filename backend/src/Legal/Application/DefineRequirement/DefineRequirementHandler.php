<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\DefineRequirement;

use Eventor\Legal\Contract\Exception\LegalDocumentNotFound;
use Eventor\Legal\Domain\Document\LegalDocumentRepository;
use Eventor\Legal\Domain\Requirement\LegalRequirement;
use Eventor\Legal\Domain\Requirement\LegalRequirementRepository;
use Eventor\Legal\Domain\Requirement\RequirementPolicy;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\VariantId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DefineRequirementHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private LegalRequirementRepository $requirements,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{requirementId: string}
     */
    public function __invoke(DefineRequirement $command): array
    {
        $documentId = LegalDocumentId::fromString($command->documentId);
        $document = $this->documents->find($documentId) ?? throw LegalDocumentNotFound::forId($documentId);

        $requirement = LegalRequirement::define(
            LegalRequirementId::generate(),
            $document->organizerId(),
            $documentId,
            $command->type,
            $command->purpose,
            $command->scopeText,
            $command->validity,
            $command->confirmer,
            $command->revocable ?? RequirementPolicy::isVoluntary($command->type),
            $command->mandatory,
            $command->preChecked,
            null === $command->activityId ? null : ActivityId::fromString($command->activityId),
            null === $command->variantId ? null : VariantId::fromString($command->variantId),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->requirements->add($requirement);

        return ['requirementId' => $requirement->id()->toString()];
    }
}
