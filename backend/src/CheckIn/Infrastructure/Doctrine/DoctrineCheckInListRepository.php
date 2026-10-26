<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\CheckIn\Domain\CheckInList;
use Eventor\CheckIn\Domain\CheckInListRepository;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CheckInListRepository::class)]
final readonly class DoctrineCheckInListRepository implements CheckInListRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(CheckInList $list): void
    {
        $this->em->persist($list);
    }

    public function find(CheckInListId $id): ?CheckInList
    {
        return $this->em->find(CheckInList::class, $id);
    }

    public function findForActivity(ActivityId $activity): ?CheckInList
    {
        return $this->em->getRepository(CheckInList::class)->findOneBy(['activityId' => $activity]);
    }

    public function forEvent(EventId $event): array
    {
        return array_values($this->em->getRepository(CheckInList::class)->findBy(['eventId' => $event]));
    }
}
