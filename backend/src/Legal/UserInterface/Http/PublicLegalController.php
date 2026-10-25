<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http;

use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Legal\Application\Query\Cells;
use Eventor\Legal\Application\Query\DocumentQuery;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Public, unauthenticated read of PUBLISHED legal content for the registration form (LEG-03). Drafts are invisible.
 * The content of a version never changes, so responses are cacheable for good and carry the SHA-256 as ETag.
 */
#[AsController]
final class PublicLegalController extends AbstractController
{
    public function __construct(
        private readonly OrganizerLookup $organizers,
        private readonly DocumentQuery $documents,
    ) {}

    #[Route('/api/public/orgs/{orgSlug}/legal/versions/{versionId}', name: 'api_public_legal_version', methods: ['GET'], requirements: ['versionId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'A published version of a legal document (metadata and, for text, the Markdown text)',
        tags: ['legal-public'],
        responses: [new OA\Response(response: 200, description: 'Version'), new OA\Response(response: 404, description: 'not_found: unknown organizer, unknown or unpublished version')],
    )]
    public function version(string $orgSlug, string $versionId): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);
        $version = $this->documents->publishedVersion($organizer->id, DocumentVersionId::fromString($versionId)) ?? throw new NotFoundHttpException('Legal document version not found.');

        $version['contentUrl'] = \sprintf('/api/public/orgs/%s/legal/versions/%s/content', $organizer->slug, $versionId);
        $response = $this->json($version);
        $this->makeImmutable($response, Cells::string($version['sha256']));

        return $response;
    }

    #[Route('/api/public/orgs/{orgSlug}/legal/versions/{versionId}/content', name: 'api_public_legal_version_content', methods: ['GET'], requirements: ['versionId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'The raw bytes of a published version (Markdown, HTML, plain text or PDF)',
        tags: ['legal-public'],
        responses: [new OA\Response(response: 200, description: 'The content with its media type'), new OA\Response(response: 404, description: 'not_found')],
    )]
    public function versionContent(string $orgSlug, string $versionId): Response
    {
        $organizer = $this->organizer($orgSlug);
        $version = $this->documents->publishedVersion($organizer->id, DocumentVersionId::fromString($versionId)) ?? throw new NotFoundHttpException('Legal document version not found.');

        return $this->bytes($this->contentRow($organizer, Cells::string($version['sha256'])));
    }

    #[Route('/api/public/orgs/{orgSlug}/legal/content/{sha256}', name: 'api_public_legal_content_by_hash', methods: ['GET'], requirements: ['sha256' => '[0-9a-fA-F]{64}'])]
    #[OA\Get(
        summary: 'The raw bytes of a published version by the SHA-256 of its content',
        tags: ['legal-public'],
        responses: [new OA\Response(response: 200, description: 'The content with its media type'), new OA\Response(response: 404, description: 'not_found')],
    )]
    public function contentByHash(string $orgSlug, string $sha256): Response
    {
        return $this->bytes($this->contentRow($this->organizer($orgSlug), $sha256));
    }

    private function organizer(string $slug): OrganizerView
    {
        return $this->organizers->findBySlug($slug) ?? throw new NotFoundHttpException('Organizer not found.');
    }

    /**
     * @return array<string, mixed>
     */
    private function contentRow(OrganizerView $organizer, string $sha256): array
    {
        return $this->documents->publishedByHash($organizer->id, $sha256) ?? throw new NotFoundHttpException('Legal document content not found.');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function bytes(array $row): Response
    {
        $response = new Response($this->documents->bytes(Cells::string($row['storage_key'])), Response::HTTP_OK, [
            'Content-Type' => Cells::string($row['mime']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
        $this->makeImmutable($response, Cells::string($row['sha256']));

        return $response;
    }

    private function makeImmutable(Response $response, string $sha256): void
    {
        $response->setEtag($sha256);
        $response->setPublic();
        $response->setMaxAge(86400);
    }
}
