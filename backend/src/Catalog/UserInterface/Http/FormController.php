<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Eventor\Catalog\Application\Form\EditDraftForm;
use Eventor\Catalog\Application\Form\PublishFormVersion;
use Eventor\Catalog\Application\Query\AdminFormQuery;
use Eventor\Catalog\Application\Query\FieldTypeCatalogQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The form editor API (FRM-01..09): draft, immutable versions, field types. The schema is a JSON object
 * `{"fields": [...]}`; an invalid one is a 422 `form_schema_invalid` that lists every problem with its path.
 */
#[AsController]
final class FormController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AdminFormQuery $forms,
        private readonly FieldTypeCatalogQuery $fieldTypes,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/form', name: 'api_admin_form_overview', methods: ['GET'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'The draft and the latest published version of the registration form, and the list of versions',
        tags: ['form'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'draft, published, versions'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
        ],
    )]
    public function overview(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json($this->forms->overview($organizer->id, EventId::fromString($eventId)));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/form/versions/{number}', name: 'api_admin_form_version', methods: ['GET'], requirements: ['eventId' => Requirement::UUID, 'number' => '\d+'])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'One version of the form (draft or published)',
        tags: ['form'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'number', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The version with its fields'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / form_version_not_found'),
        ],
    )]
    public function version(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, int $number): JsonResponse
    {
        return $this->json($this->forms->version($organizer->id, EventId::fromString($eventId), $number));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/form/draft', name: 'api_admin_form_edit_draft', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Put(
        summary: 'Replace the draft of the form; after the publication this starts the draft of the next version',
        tags: ['form'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['fields'],
            properties: [new OA\Property(property: 'fields', type: 'array', items: new OA\Items(type: 'object'))],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The draft as saved (overview)'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 422, description: 'form_schema_invalid: `violations` lists every problem'),
        ],
    )]
    public function editDraft(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, Request $request): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new EditDraftForm($id, $request->getPayload()->all()));

        return $this->json($this->forms->overview($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/form/publish', name: 'api_admin_form_publish', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Post(
        summary: 'Freeze the draft as the next immutable form version of a published event (FRM-08, FRM-09)',
        tags: ['form'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'formVersionId and number'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 422, description: 'invalid_catalog_data (no draft, event not published) / event_not_publishable'),
        ],
    )]
    public function publish(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new PublishFormVersion(EventId::fromString($eventId))));
    }

    #[Route('/api/admin/org/{orgSlug}/form-field-types', name: 'api_admin_form_field_types', methods: ['GET'])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'Field types the editor offers, with their constraints and semantic fields',
        tags: ['form'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'types and semanticFields'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
        ],
    )]
    public function fieldTypes(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json($this->fieldTypes->describe());
    }
}
