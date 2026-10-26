<?php

declare(strict_types=1);

namespace Eventor\Content\UserInterface\Http;

use Eventor\Content\Application\AddMaterial\AddMaterial;
use Eventor\Content\Application\CreateCollection\CreateCollection;
use Eventor\Content\Application\ProposeClosingMessage\ProposeClosingMessage;
use Eventor\Content\Application\Query\AdminContentQuery;
use Eventor\Content\Application\UpdateCollection\UpdateCollection;
use Eventor\Content\Application\UpdateMaterial\UpdateMaterial;
use Eventor\Content\Application\UploadPhoto\UploadPhoto;
use Eventor\Content\Contract\CollectionVisibility;
use Eventor\Content\Contract\Exception\InvalidPhoto;
use Eventor\Content\Contract\MaterialType;
use Eventor\Content\UserInterface\Http\Request\AddMaterialRequest;
use Eventor\Content\UserInterface\Http\Request\ArchiveRequest;
use Eventor\Content\UserInterface\Http\Request\CreateCollectionRequest;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Post-event content of one event (POST-01..05, POST-07). Every route carries `{eventId}`, so a member restricted to
 * some events manages only theirs (ORG-03).
 */
final class AdminContentController extends AbstractController
{
    private const string BASE = '/api/admin/org/{orgSlug}/events/{eventId}';
    private const array IDS = ['eventId' => Requirement::UUID, 'collectionId' => Requirement::UUID, 'materialId' => Requirement::UUID];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly AdminContentQuery $content,
    ) {}

    #[Route(self::BASE.'/collections', name: 'api_admin_content_collections', methods: ['GET'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Get(summary: 'Collections of the event, drafts included', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{items: [...]}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found'),
    ])]
    public function collections(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json(['items' => $this->content->collections($organizer->id, EventId::fromString($eventId))]);
    }

    #[Route(self::BASE.'/collections', name: 'api_admin_content_collection_create', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Create a draft gallery (POST-01) or external collection (POST-02), public or link-only (POST-03)', tags: ['content'], responses: [
        new OA\Response(response: 201, description: '{collectionId, linkToken}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or event_not_found (unknown or draft event)'),
        new OA\Response(response: 422, description: 'validation_failed'),
    ])]
    public function createCollection(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] CreateCollectionRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new CreateCollection($eventId, $body->title, CollectionVisibility::from($body->visibility), $body->externalUrl)), Response::HTTP_CREATED);
    }

    #[Route(self::BASE.'/collections/{collectionId}/photos', name: 'api_admin_content_photos', methods: ['GET'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Get(summary: 'Photos of a collection', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{items: [...]}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or collection_not_found'),
    ])]
    public function photos(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $collectionId): JsonResponse
    {
        return $this->json(['items' => $this->content->photos($organizer->id, EventId::fromString($eventId), CollectionId::fromString($collectionId))]);
    }

    #[Route(self::BASE.'/collections/{collectionId}/photos', name: 'api_admin_content_photo_upload', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(
        summary: 'Upload a photo into a gallery (multipart: photo + description, author, eventPart, position); the thumbnail follows asynchronously',
        tags: ['content'],
        requestBody: new OA\RequestBody(content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['photo'], properties: [
            new OA\Property(property: 'photo', type: 'string', format: 'binary'),
            new OA\Property(property: 'description', type: 'string'),
            new OA\Property(property: 'author', type: 'string'),
            new OA\Property(property: 'eventPart', type: 'string'),
            new OA\Property(property: 'position', type: 'integer'),
        ]))),
        responses: [
            new OA\Response(response: 201, description: '{photoId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or collection_not_found'),
            new OA\Response(response: 409, description: 'collection_is_external'),
            new OA\Response(response: 422, description: 'invalid_photo'),
        ],
    )]
    public function uploadPhoto(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $collectionId, Request $request): JsonResponse
    {
        $file = $request->files->get('photo');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw InvalidPhoto::because('Send the image as the multipart field "photo".');
        }
        $position = $request->request->get('position');

        return $this->json($this->commands->dispatch(new UploadPhoto(
            $eventId,
            $collectionId,
            $file->getContent(),
            self::text($request, 'description', 1000),
            self::text($request, 'author', 200),
            self::text($request, 'eventPart', 200),
            is_numeric($position) ? max(0, (int) $position) : null,
        )), Response::HTTP_CREATED);
    }

    #[Route(self::BASE.'/collections/{collectionId}/publish', name: 'api_admin_content_collection_publish', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Publish a collection (emits content.collection_published)', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{collectionId, published}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or collection_not_found'),
    ])]
    public function publishCollection(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $collectionId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new UpdateCollection($eventId, $collectionId, published: true)));
    }

    #[Route(self::BASE.'/collections/{collectionId}/unpublish', name: 'api_admin_content_collection_unpublish', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Take a collection back to draft (e.g. a photo has to go)', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{collectionId, published}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or collection_not_found'),
    ])]
    public function unpublishCollection(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $collectionId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new UpdateCollection($eventId, $collectionId, published: false)));
    }

    #[Route(self::BASE.'/collections/{collectionId}/archive', name: 'api_admin_content_collection_archive', methods: ['PUT'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Put(summary: 'Put a collection into (or out of) the public archive (POST-07)', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{collectionId, published}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or collection_not_found'),
        new OA\Response(response: 422, description: 'validation_failed'),
    ])]
    public function archiveCollection(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $collectionId, #[MapRequestPayload] ArchiveRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new UpdateCollection($eventId, $collectionId, inArchive: $body->inArchive)));
    }

    #[Route(self::BASE.'/materials', name: 'api_admin_content_materials', methods: ['GET'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Get(summary: 'Materials of the event, drafts included', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{items: [...]}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found'),
    ])]
    public function materials(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json(['items' => $this->content->materials($organizer->id, EventId::fromString($eventId))]);
    }

    #[Route(self::BASE.'/materials', name: 'api_admin_content_material_add', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Add a draft material link: results, video, document, partners (POST-04)', tags: ['content'], responses: [
        new OA\Response(response: 201, description: '{materialId}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or event_not_found'),
        new OA\Response(response: 422, description: 'validation_failed'),
    ])]
    public function addMaterial(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] AddMaterialRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new AddMaterial($eventId, $body->title, $body->url, MaterialType::from($body->type), $body->position)), Response::HTTP_CREATED);
    }

    #[Route(self::BASE.'/materials/{materialId}/publish', name: 'api_admin_content_material_publish', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Publish a material', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{materialId}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or material_not_found'),
    ])]
    public function publishMaterial(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $materialId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new UpdateMaterial($eventId, $materialId, published: true)));
    }

    #[Route(self::BASE.'/materials/{materialId}/unpublish', name: 'api_admin_content_material_unpublish', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Take a material back to draft', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{materialId}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or material_not_found'),
    ])]
    public function unpublishMaterial(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $materialId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new UpdateMaterial($eventId, $materialId, published: false)));
    }

    #[Route(self::BASE.'/materials/{materialId}/archive', name: 'api_admin_content_material_archive', methods: ['PUT'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Put(summary: 'Put a material into (or out of) the public archive (POST-07)', tags: ['content'], responses: [
        new OA\Response(response: 200, description: '{materialId}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or material_not_found'),
        new OA\Response(response: 422, description: 'validation_failed'),
    ])]
    public function archiveMaterial(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $materialId, #[MapRequestPayload] ArchiveRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new UpdateMaterial($eventId, $materialId, inArchive: $body->inArchive)));
    }

    #[Route(self::BASE.'/closing-message', name: 'api_admin_content_closing_message', methods: ['POST'], requirements: self::IDS)]
    #[RequiresPermission(Permission::ContentManage)]
    #[OA\Post(summary: 'Propose a draft "thank you" campaign to the checked-in participants (POST-05); send it in Communication', tags: ['content'], responses: [
        new OA\Response(response: 201, description: '{campaignId}'),
        new OA\Response(response: 401, description: 'unauthorized'),
        new OA\Response(response: 403, description: 'forbidden'),
        new OA\Response(response: 404, description: 'not_found or event_not_found'),
    ])]
    public function closingMessage(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new ProposeClosingMessage($eventId)), Response::HTTP_CREATED);
    }

    private static function text(Request $request, string $field, int $maxLength): ?string
    {
        $value = trim($request->request->getString($field));

        return '' === $value ? null : mb_substr($value, 0, $maxLength);
    }
}
