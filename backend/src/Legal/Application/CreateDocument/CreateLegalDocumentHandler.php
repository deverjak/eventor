<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\CreateDocument;

use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\Exception\LegalDocumentNotFound;
use Eventor\Legal\Domain\Document\LegalDocument;
use Eventor\Legal\Domain\Document\LegalDocumentRepository;
use Eventor\Legal\Domain\Exception\InvalidDocument;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateLegalDocumentHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private TenantContext $tenant,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{documentId: string}
     */
    public function __invoke(CreateLegalDocument $command): array
    {
        $replaces = null;
        if (null !== $command->replacesDocumentId) {
            $replacesId = LegalDocumentId::fromString($command->replacesDocumentId);
            $replaces = $this->documents->find($replacesId) ?? throw LegalDocumentNotFound::forId($replacesId);
        }

        $organizer = $this->tenant->require();
        $scopeId = $command->scopeId ?? (DocumentLevel::Organizer === $command->level ? $organizer->toString() : throw InvalidDocument::because('The scopeId (event or activity id) is required for event and activity documents.'));

        $document = LegalDocument::create(
            LegalDocumentId::generate(),
            $organizer,
            $command->level,
            strtolower($scopeId),
            $command->title,
            $replaces,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->documents->add($document);

        return ['documentId' => $document->id()->toString()];
    }
}
