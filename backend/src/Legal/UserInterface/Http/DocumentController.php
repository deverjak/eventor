<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Legal\Application\CreateDocument\CreateLegalDocument;
use Eventor\Legal\Application\DraftVersion\DraftDocumentVersion;
use Eventor\Legal\Application\PublishVersion\PublishDocumentVersion;
use Eventor\Legal\Application\Query\DocumentQuery;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\UserInterface\Http\Request\CreateDocumentRequest;
use Eventor\Legal\UserInterface\Http\Request\DraftVersionRequest;
use Eventor\Legal\UserInterface\Http\Request\PublishVersionRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Admin API of legal documents and their immutable versions (LEG-01 to LEG-03). Organizer-level documents need the
 * owner (`legal.organizer.publish`), event and activity documents the event manager (`legal.event.manage`).
 */
#[AsController]
#[RequiresPermission(Permission::EventLegalManage)]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly DocumentQuery $documents,
        private readonly LegalWriteGuard $guard,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/legal/documents', name: 'api_admin_legal_document_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create an empty legal document (organizer, event or activity level)',
        tags: ['legal'],
        responses: [
            new OA\Response(response: 201, description: 'Created: {documentId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found: organizer, or the replaced document'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_legal_document'),
        ],
    )]
    public function create(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] CreateDocumentRequest $body): JsonResponse
    {
        $this->guard->assertMayChange($organizer, $body->level);
        /** @var array{documentId: string} $result */
        $result = $this->commands->dispatch(new CreateLegalDocument($body->level, $body->scopeId, $body->title, $body->replacesDocumentId));

        return $this->json($result, Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/legal/documents', name: 'api_admin_legal_document_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List legal documents, optionally of one level and scope',
        tags: ['legal'],
        parameters: [
            new OA\Parameter(name: 'level', in: 'query', schema: new OA\Schema(type: 'string', enum: ['organizer', 'event', 'activity'])),
            new OA\Parameter(name: 'scopeId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OA\Response(response: 200, description: '{items: [...]}'), new OA\Response(response: 401, description: 'unauthorized'), new OA\Response(response: 403, description: 'forbidden'), new OA\Response(response: 404, description: 'not_found')],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $level = DocumentLevel::tryFrom($request->query->getString('level'));
        $scope = $request->query->getString('scopeId');

        return $this->json(['items' => $this->documents->list($organizer->id, $level, Uuid::isValid($scope) ? $scope : null)]);
    }

    #[Route('/api/admin/org/{orgSlug}/legal/documents/{documentId}', name: 'api_admin_legal_document_get', methods: ['GET'], requirements: ['documentId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'A legal document with all its versions',
        tags: ['legal'],
        responses: [new OA\Response(response: 200, description: 'Document with versions'), new OA\Response(response: 401, description: 'unauthorized'), new OA\Response(response: 403, description: 'forbidden'), new OA\Response(response: 404, description: 'not_found or legal_document_not_found')],
    )]
    public function get(#[CurrentOrganizer] OrganizerView $organizer, string $documentId): JsonResponse
    {
        return $this->json($this->documents->get($organizer->id, LegalDocumentId::fromString($documentId)));
    }

    #[Route('/api/admin/org/{orgSlug}/legal/documents/{documentId}/versions', name: 'api_admin_legal_version_draft', methods: ['POST'], requirements: ['documentId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Upload the next version as a draft: Markdown text, a base64 PDF or an external link (snapshot taken now)',
        tags: ['legal'],
        responses: [
            new OA\Response(response: 201, description: 'Draft stored: {versionId, number, sha256}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or legal_document_not_found'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_legal_document'),
        ],
    )]
    public function draft(#[CurrentOrganizer] OrganizerView $organizer, string $documentId, #[MapRequestPayload] DraftVersionRequest $body): JsonResponse
    {
        $this->guard->assertMayChange($organizer, $this->documents->levelOf($organizer->id, LegalDocumentId::fromString($documentId)));

        return $this->json(
            $this->commands->dispatch(new DraftDocumentVersion($documentId, $body->kind, $body->text, $body->pdfBase64, $body->url)),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/admin/org/{orgSlug}/legal/documents/{documentId}/versions/{versionId}', name: 'api_admin_legal_version_get', methods: ['GET'], requirements: ['documentId' => Requirement::UUID, 'versionId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'One version incl. its text (draft or published)',
        tags: ['legal'],
        responses: [new OA\Response(response: 200, description: 'Version'), new OA\Response(response: 401, description: 'unauthorized'), new OA\Response(response: 403, description: 'forbidden'), new OA\Response(response: 404, description: 'not_found or legal_document_not_found')],
    )]
    public function version(#[CurrentOrganizer] OrganizerView $organizer, string $documentId, string $versionId): JsonResponse
    {
        return $this->json($this->documents->version($organizer->id, LegalDocumentId::fromString($documentId), DocumentVersionId::fromString($versionId)));
    }

    #[Route('/api/admin/org/{orgSlug}/legal/documents/{documentId}/versions/{versionId}/publish', name: 'api_admin_legal_version_publish', methods: ['POST'], requirements: ['documentId' => Requirement::UUID, 'versionId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Publish a draft: it becomes the current immutable version; optionally everybody must confirm again',
        tags: ['legal'],
        responses: [
            new OA\Response(response: 200, description: 'Published: {versionId, number, requiresReconfirmation}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or legal_document_not_found'),
            new OA\Response(response: 409, description: 'document_version_already_published'),
        ],
    )]
    public function publish(#[CurrentOrganizer] OrganizerView $organizer, string $documentId, string $versionId, #[MapRequestPayload] PublishVersionRequest $body): JsonResponse
    {
        $this->guard->assertMayChange($organizer, $this->documents->levelOf($organizer->id, LegalDocumentId::fromString($documentId)));

        return $this->json($this->commands->dispatch(new PublishDocumentVersion($documentId, $versionId, $body->requiresReconfirmation)));
    }
}
