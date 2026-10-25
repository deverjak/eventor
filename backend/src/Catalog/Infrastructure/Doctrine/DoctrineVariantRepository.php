<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Catalog\Domain\Exception\VariantNotFound;
use Eventor\Catalog\Domain\Structure\Variant;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(VariantRepository::class)]
final readonly class DoctrineVariantRepository implements VariantRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(EventId $event, VariantId $id): Variant
    {
        return $this->em->getRepository(Variant::class)->findOneBy(['id' => $id, 'eventId' => $event]) ?? throw VariantNotFound::forId($id);
    }

    public function forEvent(EventId $event): array
    {
        return array_values($this->em->getRepository(Variant::class)->findBy(['eventId' => $event], ['position' => 'ASC', 'createdAt' => 'ASC']));
    }

    public function forActivity(ActivityId $activity): array
    {
        return array_values($this->em->getRepository(Variant::class)->findBy(['activityId' => $activity], ['position' => 'ASC', 'createdAt' => 'ASC']));
    }

    public function nextPosition(ActivityId $activity): int
    {
        $max = $this->em->createQuery('SELECT MAX(v.position) FROM '.Variant::class.' v WHERE v.activityId = :activity')->setParameter('activity', $activity)->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }

    public function add(Variant $variant): void
    {
        $this->em->persist($variant);
    }

    public function remove(Variant $variant): void
    {
        $this->em->remove($variant);
    }
}
