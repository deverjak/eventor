<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Catalog\Domain\Form\FormVersion;
use Eventor\Catalog\Domain\Form\FormVersionRepository;
use Eventor\Shared\Domain\Id\EventId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(FormVersionRepository::class)]
final readonly class DoctrineFormVersionRepository implements FormVersionRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function draftOf(EventId $event): ?FormVersion
    {
        $draft = $this->em->createQuery('SELECT f FROM '.FormVersion::class.' f WHERE f.eventId = :event AND f.publishedAt IS NULL')
            ->setParameter('event', $event)
            ->getOneOrNullResult();
        \assert(null === $draft || $draft instanceof FormVersion);

        return $draft;
    }

    public function latestPublished(EventId $event): ?FormVersion
    {
        $latest = $this->em->createQuery('SELECT f FROM '.FormVersion::class.' f WHERE f.eventId = :event AND f.publishedAt IS NOT NULL ORDER BY f.number DESC')
            ->setParameter('event', $event)
            ->setMaxResults(1)
            ->getOneOrNullResult();
        \assert(null === $latest || $latest instanceof FormVersion);

        return $latest;
    }

    public function highestNumber(EventId $event): int
    {
        $max = $this->em->createQuery('SELECT MAX(f.number) FROM '.FormVersion::class.' f WHERE f.eventId = :event')->setParameter('event', $event)->getSingleScalarResult();

        return null === $max ? 0 : (int) $max;
    }

    public function add(FormVersion $version): void
    {
        $this->em->persist($version);
    }
}
