<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Catalog\Domain\Exception\ActivityNotFound;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ActivityRepository::class)]
final readonly class DoctrineActivityRepository implements ActivityRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(EventId $event, ActivityId $id): Activity
    {
        return $this->em->getRepository(Activity::class)->findOneBy(['id' => $id, 'eventId' => $event]) ?? throw ActivityNotFound::forId($id);
    }

    public function forEvent(EventId $event): array
    {
        return array_values($this->em->getRepository(Activity::class)->findBy(['eventId' => $event], ['position' => 'ASC', 'createdAt' => 'ASC']));
    }

    public function nextPosition(EventId $event): int
    {
        $max = $this->em->createQuery('SELECT MAX(a.position) FROM '.Activity::class.' a WHERE a.eventId = :event')->setParameter('event', $event)->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }

    public function add(Activity $activity): void
    {
        $this->em->persist($activity);
    }

    public function remove(Activity $activity): void
    {
        $this->em->remove($activity);
    }
}
