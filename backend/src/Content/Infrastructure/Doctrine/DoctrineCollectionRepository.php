<?php

declare(strict_types=1);

namespace Eventor\Content\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Content\Domain\Collection\Collection;
use Eventor\Content\Domain\Collection\CollectionRepository;
use Eventor\Content\Domain\Collection\Photo;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\PhotoId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CollectionRepository::class)]
final readonly class DoctrineCollectionRepository implements CollectionRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function find(CollectionId $id): ?Collection
    {
        return $this->em->getRepository(Collection::class)->find($id);
    }

    public function add(Collection $collection): void
    {
        $this->em->persist($collection);
    }

    public function findPhoto(PhotoId $id): ?Photo
    {
        return $this->em->getRepository(Photo::class)->find($id);
    }

    public function addPhoto(Photo $photo): void
    {
        $this->em->persist($photo);
    }

    public function nextPhotoPosition(CollectionId $collection): int
    {
        $max = $this->em->createQuery('SELECT MAX(p.position) FROM '.Photo::class.' p WHERE p.collectionId = :collection')
            ->setParameter('collection', $collection, 'collection_id')
            ->getSingleScalarResult();

        return is_numeric($max) ? (int) $max + 1 : 0;
    }
}
