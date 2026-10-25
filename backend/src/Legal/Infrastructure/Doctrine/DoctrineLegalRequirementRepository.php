<?php

declare(strict_types=1);

namespace Eventor\Legal\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Legal\Domain\Requirement\LegalRequirement;
use Eventor\Legal\Domain\Requirement\LegalRequirementRepository;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(LegalRequirementRepository::class)]
final readonly class DoctrineLegalRequirementRepository implements LegalRequirementRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function find(LegalRequirementId $id): ?LegalRequirement
    {
        return $this->em->getRepository(LegalRequirement::class)->find($id);
    }

    public function add(LegalRequirement $requirement): void
    {
        $this->em->persist($requirement);
    }

    public function activeOfDocument(LegalDocumentId $document): array
    {
        /** @var list<LegalRequirement> $requirements */
        $requirements = $this->em->createQuery('SELECT r FROM '.LegalRequirement::class.' r WHERE r.documentId = :d AND r.archivedAt IS NULL ORDER BY r.createdAt, r.id')
            ->setParameter('d', $document, 'legal_document_id')
            ->getResult();

        return $requirements;
    }
}
