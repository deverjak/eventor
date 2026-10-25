<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Organizer\Organizer;
use Eventor\Identity\Domain\Organizer\OrganizerRepository;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OrganizerRepository::class)]
final readonly class DoctrineOrganizerRepository implements OrganizerRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function findBySlug(string $slug): ?Organizer
    {
        return $this->em->getRepository(Organizer::class)->findOneBy(['slug' => $slug]);
    }

    public function findById(OrganizerId $id): ?Organizer
    {
        return $this->em->getRepository(Organizer::class)->findOneBy(['id' => $id]);
    }

    public function add(Organizer $organizer): void
    {
        $this->em->persist($organizer);
    }
}
