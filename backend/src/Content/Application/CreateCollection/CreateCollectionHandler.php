<?php

declare(strict_types=1);

namespace Eventor\Content\Application\CreateCollection;

use Eventor\Content\Application\Port\EventGuard;
use Eventor\Content\Domain\Collection\Collection;
use Eventor\Content\Domain\Collection\CollectionRepository;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private EventGuard $events,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{collectionId: string, linkToken: string|null}
     */
    public function __invoke(CreateCollection $command): array
    {
        $organizer = $this->tenant->require();
        $event = EventId::fromString($command->eventId);
        $this->events->assertExists($organizer, $event);

        $collection = Collection::create(CollectionId::generate(), $organizer, $event, $command->title, $command->visibility, $command->externalUrl, \DateTimeImmutable::createFromInterface($this->clock->now()));
        $this->collections->add($collection);

        return ['collectionId' => $collection->id()->toString(), 'linkToken' => $collection->linkToken()];
    }
}
