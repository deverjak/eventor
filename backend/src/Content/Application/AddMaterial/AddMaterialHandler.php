<?php

declare(strict_types=1);

namespace Eventor\Content\Application\AddMaterial;

use Eventor\Content\Application\Port\EventGuard;
use Eventor\Content\Domain\Material\Material;
use Eventor\Content\Domain\Material\MaterialRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MaterialId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AddMaterialHandler
{
    public function __construct(
        private MaterialRepository $materials,
        private EventGuard $events,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{materialId: string}
     */
    public function __invoke(AddMaterial $command): array
    {
        $organizer = $this->tenant->require();
        $event = EventId::fromString($command->eventId);
        $this->events->assertExists($organizer, $event);

        $material = Material::add(MaterialId::generate(), $organizer, $event, $command->title, $command->url, $command->type, $command->position, \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->materials->add($material);

        return ['materialId' => $material->id()->toString()];
    }
}
