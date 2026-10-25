<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Shared\Domain\Id\EventId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(EventRepository::class)]
final readonly class DoctrineEventRepository implements EventRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(EventId $id): Event
    {
        return $this->em->getRepository(Event::class)->findOneBy(['id' => $id]) ?? throw EventNotFound::forId($id);
    }

    public function slugExists(string $slug): bool
    {
        return null !== $this->em->getRepository(Event::class)->findOneBy(['slug' => $slug]);
    }

    public function add(Event $event): void
    {
        $this->em->persist($event);
    }
}
