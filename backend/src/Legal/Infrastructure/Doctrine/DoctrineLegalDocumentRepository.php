<?php

declare(strict_types=1);

namespace Eventor\Legal\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Legal\Domain\Document\DocumentVersion;
use Eventor\Legal\Domain\Document\LegalDocument;
use Eventor\Legal\Domain\Document\LegalDocumentRepository;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(LegalDocumentRepository::class)]
final readonly class DoctrineLegalDocumentRepository implements LegalDocumentRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function find(LegalDocumentId $id): ?LegalDocument
    {
        return $this->em->getRepository(LegalDocument::class)->find($id);
    }

    public function add(LegalDocument $document): void
    {
        $this->em->persist($document);
    }

    public function findVersion(DocumentVersionId $id): ?DocumentVersion
    {
        return $this->em->getRepository(DocumentVersion::class)->find($id);
    }

    public function nextVersionNumber(LegalDocumentId $document): int
    {
        $max = $this->em->createQuery('SELECT MAX(v.number) FROM '.DocumentVersion::class.' v WHERE v.documentId = :d')
            ->setParameter('d', $document, 'legal_document_id')
            ->getSingleScalarResult();

        return (int) $max + 1;
    }

    public function findDraft(LegalDocumentId $document): ?DocumentVersion
    {
        $draft = $this->em->createQuery('SELECT v FROM '.DocumentVersion::class.' v WHERE v.documentId = :d AND v.publishedAt IS NULL')
            ->setParameter('d', $document, 'legal_document_id')
            ->setMaxResults(1)
            ->getOneOrNullResult();

        return $draft instanceof DocumentVersion ? $draft : null;
    }

    public function addVersion(DocumentVersion $version): void
    {
        $this->em->persist($version);
    }

    public function removeDraft(DocumentVersion $draft): void
    {
        if ($draft->isPublished()) {
            throw new \LogicException('A published version cannot be removed.');
        }

        $this->em->remove($draft);
        // Flushed at once: Doctrine runs deletes after inserts, and the replacement reuses the draft number.
        $this->em->flush();
    }
}
