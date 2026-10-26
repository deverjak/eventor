<?php

declare(strict_types=1);

namespace Eventor\Content\Application\UpdateMaterial;

use Eventor\Content\Domain\Exception\MaterialNotFound;
use Eventor\Content\Domain\Material\MaterialRepository;
use Eventor\Shared\Domain\Id\MaterialId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('Repeating a state the material already has changes and records nothing.')]
final readonly class UpdateMaterialHandler
{
    public function __construct(private MaterialRepository $materials, private ClockInterface $clock) {}

    /**
     * @return array{materialId: string}
     */
    public function __invoke(UpdateMaterial $command): array
    {
        $id = MaterialId::fromString($command->materialId);
        $material = $this->materials->find($id);
        if (null === $material || $material->eventId()->toString() !== $command->eventId) {
            throw MaterialNotFound::forId($id);
        }
        if (true === $command->published) {
            $material->publish(\DateTimeImmutable::createFromInterface($this->clock->now()));
        } elseif (false === $command->published) {
            $material->unpublish();
        }
        if (null !== $command->inArchive) {
            $material->designateForArchive($command->inArchive);
        }

        return ['materialId' => $id->toString()];
    }
}
