<?php

declare(strict_types=1);

namespace Eventor\Content\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Content\Domain\Material\Material;
use Eventor\Content\Domain\Material\MaterialRepository;
use Eventor\Shared\Domain\Id\MaterialId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MaterialRepository::class)]
final readonly class DoctrineMaterialRepository implements MaterialRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function find(MaterialId $id): ?Material
    {
        return $this->em->getRepository(Material::class)->find($id);
    }

    public function add(Material $material): void
    {
        $this->em->persist($material);
    }
}
