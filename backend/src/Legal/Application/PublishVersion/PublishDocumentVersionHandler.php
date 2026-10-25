<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\PublishVersion;

use Eventor\Legal\Contract\Exception\LegalDocumentNotFound;
use Eventor\Legal\Domain\Document\LegalDocumentRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishDocumentVersionHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{versionId: string, number: int, requiresReconfirmation: bool}
     */
    public function __invoke(PublishDocumentVersion $command): array
    {
        $documentId = LegalDocumentId::fromString($command->documentId);
        $document = $this->documents->find($documentId) ?? throw LegalDocumentNotFound::forId($documentId);
        $version = $this->documents->findVersion(DocumentVersionId::fromString($command->versionId));
        if (null === $version || !$version->documentId()->equals($documentId)) {
            throw LegalDocumentNotFound::forId($documentId);
        }

        $document->publish($version, $command->requiresReconfirmation, \DateTimeImmutable::createFromInterface($this->clock->now()), $this->audit->actor()?->id);

        return ['versionId' => $version->id()->toString(), 'number' => $version->number(), 'requiresReconfirmation' => $version->requiresReconfirmation()];
    }
}
