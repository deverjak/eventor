<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Communication\Contract\SuppressionScope;
use Eventor\Communication\Domain\Suppression\Suppression;
use Eventor\Communication\Domain\Suppression\SuppressionId;
use Eventor\Communication\Domain\Suppression\SuppressionRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SuppressionRepository::class)]
final readonly class DoctrineSuppressionRepository implements SuppressionRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(Suppression $suppression): void
    {
        $this->em->persist($suppression);
    }

    public function remove(Suppression $suppression): void
    {
        $this->em->remove($suppression);
    }

    public function find(SuppressionId $id): ?Suppression
    {
        return $this->em->getRepository(Suppression::class)->findOneBy(['id' => $id]);
    }

    public function findByHash(string $emailSha256, SuppressionScope $scope): ?Suppression
    {
        return $this->em->getRepository(Suppression::class)->findOneBy(['emailSha256' => $emailSha256, 'scope' => $scope]);
    }
}
