<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Shared\Domain\Event\RecordsEventsInterface;

/**
 * Pulls the domain events recorded by aggregates that are managed by the unit of work. It reads the identity map,
 * so it needs no Doctrine lifecycle listener (domain logic never runs in a listener, chapter 09).
 */
final readonly class DomainEventCollector
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @return list<object>
     */
    public function release(): array
    {
        $events = [];
        foreach ($this->em->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof RecordsEventsInterface) {
                    array_push($events, ...$entity->releaseEvents());
                }
            }
        }

        return $events;
    }
}
