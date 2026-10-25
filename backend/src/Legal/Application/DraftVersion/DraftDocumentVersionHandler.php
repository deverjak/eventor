<?php

declare(strict_types=1);

namespace Eventor\Legal\Application\DraftVersion;

use Eventor\Legal\Application\Port\DocumentContentStore;
use Eventor\Legal\Application\Port\ExternalDocumentFetcher;
use Eventor\Legal\Contract\Exception\LegalDocumentNotFound;
use Eventor\Legal\Domain\Document\CanonicalContent;
use Eventor\Legal\Domain\Document\DocumentVersion;
use Eventor\Legal\Domain\Document\LegalDocumentRepository;
use Eventor\Legal\Domain\Exception\InvalidDocument;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DraftDocumentVersionHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private DocumentContentStore $store,
        private ExternalDocumentFetcher $fetcher,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{versionId: string, number: int, sha256: string}
     */
    public function __invoke(DraftDocumentVersion $command): array
    {
        $documentId = LegalDocumentId::fromString($command->documentId);
        $document = $this->documents->find($documentId) ?? throw LegalDocumentNotFound::forId($documentId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $content = $this->canonicalize($command, $now);
        $key = $this->store->put($content);

        $previousDraft = $this->documents->findDraft($documentId);
        if (null !== $previousDraft) {
            $this->documents->removeDraft($previousDraft);
        }

        $version = DocumentVersion::draft(
            DocumentVersionId::generate(),
            $document->organizerId(),
            $documentId,
            $this->documents->nextVersionNumber($documentId),
            $content->kind,
            $content->mime,
            $content->sourceUrl,
            $content->fetchedAt,
            $content->sha256,
            $key,
            $now,
        );
        $this->documents->addVersion($version);

        return ['versionId' => $version->id()->toString(), 'number' => $version->number(), 'sha256' => $content->sha256];
    }

    private function canonicalize(DraftDocumentVersion $command, \DateTimeImmutable $now): CanonicalContent
    {
        return match ($command->kind) {
            DraftDocumentVersion::TEXT => CanonicalContent::text($command->text ?? throw InvalidDocument::because('The "text" field is required for a text document.')),
            DraftDocumentVersion::PDF => CanonicalContent::pdf($this->decode($command->pdfBase64)),
            default => $this->snapshot($command->url ?? throw InvalidDocument::because('The "url" field is required for an external link.'), $now),
        };
    }

    private function decode(?string $base64): string
    {
        $bytes = null === $base64 ? false : base64_decode($base64, true);
        if (false === $bytes) {
            throw InvalidDocument::because('The "pdfBase64" field must contain a base64 encoded PDF.');
        }

        return $bytes;
    }

    private function snapshot(string $url, \DateTimeImmutable $now): CanonicalContent
    {
        $fetched = $this->fetcher->fetch($url);

        return CanonicalContent::externalSnapshot($url, $fetched['body'], $fetched['mime'], $now);
    }
}
