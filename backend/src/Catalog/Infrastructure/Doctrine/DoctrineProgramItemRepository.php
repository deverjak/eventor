<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Catalog\Contract\Exception\ProgramItemNotFound;
use Eventor\Catalog\Domain\Program\ProgramItem;
use Eventor\Catalog\Domain\Program\ProgramItemRepository;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProgramItemRepository::class)]
final readonly class DoctrineProgramItemRepository implements ProgramItemRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(EventId $event, ProgramItemId $id): ProgramItem
    {
        return $this->em->getRepository(ProgramItem::class)->findOneBy(['id' => $id, 'eventId' => $event]) ?? throw ProgramItemNotFound::forId($id);
    }

    public function add(ProgramItem $item): void
    {
        $this->em->persist($item);
    }

    public function remove(ProgramItem $item): void
    {
        $this->em->remove($item);
    }
}
