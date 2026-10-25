<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Catalog\Contract\Exception\NewsPostNotFound;
use Eventor\Catalog\Domain\Program\NewsPost;
use Eventor\Catalog\Domain\Program\NewsPostRepository;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(NewsPostRepository::class)]
final readonly class DoctrineNewsPostRepository implements NewsPostRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(EventId $event, NewsPostId $id): NewsPost
    {
        return $this->em->getRepository(NewsPost::class)->findOneBy(['id' => $id, 'eventId' => $event]) ?? throw NewsPostNotFound::forId($id);
    }

    public function add(NewsPost $post): void
    {
        $this->em->persist($post);
    }
}
