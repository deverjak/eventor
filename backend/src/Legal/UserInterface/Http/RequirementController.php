<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Legal\Application\ArchiveRequirement\ArchiveRequirement;
use Eventor\Legal\Application\ChangeRequirement\ChangeRequirement;
use Eventor\Legal\Application\DefineRequirement\DefineRequirement;
use Eventor\Legal\Application\Query\DocumentQuery;
use Eventor\Legal\Application\Query\RequirementQuery;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Legal\UserInterface\Http\Request\ChangeRequirementRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\VariantId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Admin API of legal requirements (LEG-04 to LEG-06, LEG-11) and the preview of what applies to a registration (LEG-02).
 */
#[AsController]
#[RequiresPermission(Permission::EventLegalManage)]
final class RequirementController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly RequirementQuery $requirements,
        private readonly DocumentQuery $documents,
        private readonly RequirementResolver $resolver,
        private readonly LegalWriteGuard $guard,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/legal/requirements', name: 'api_admin_legal_requirement_define', methods: ['POST'])]
    #[OA\Post(
        summary: 'Require a document to be acknowledged or confirmed (a voluntary consent can be neither mandatory nor pre-checked)',
        tags: ['legal'],
        responses: [
            new OA\Response(response: 201, description: 'Created: {requirementId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or legal_document_not_found'),
            new OA\Response(response: 422, description: 'validation_failed (incl. consent_not_mandatory, consent_not_prechecked)'),
        ],
    )]
    public function define(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] DefineRequirement $body): JsonResponse
    {
        $this->guard->assertMayChange($organizer, $this->documents->levelOf($organizer->id, LegalDocumentId::fromString($body->documentId)));

        return $this->json($this->commands->dispatch($body), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/legal/requirements', name: 'api_admin_legal_requirement_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List requirements, optionally of one document',
        tags: ['legal'],
        parameters: [
            new OA\Parameter(name: 'documentId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'includeArchived', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: '{items: [...]}'), new OA\Response(response: 401, description: 'unauthorized'), new OA\Response(response: 403, description: 'forbidden'), new OA\Response(response: 404, description: 'not_found')],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $document = $request->query->getString('documentId');

        return $this->json(['items' => $this->requirements->list(
            $organizer->id,
            Uuid::isValid($document) ? LegalDocumentId::fromString($document) : null,
            $request->query->getBoolean('includeArchived'),
        )]);
    }

    #[Route('/api/admin/org/{orgSlug}/legal/requirements/{requirementId}', name: 'api_admin_legal_requirement_change', methods: ['PUT'], requirements: ['requirementId' => Requirement::UUID])]
    #[OA\Put(
        summary: 'Replace the editable properties of a requirement',
        tags: ['legal'],
        responses: [
            new OA\Response(response: 204, description: 'Changed'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or legal_requirement_not_found'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_requirement'),
        ],
    )]
    public function change(#[CurrentOrganizer] OrganizerView $organizer, string $requirementId, #[MapRequestPayload] ChangeRequirementRequest $body): Response
    {
        $this->assertMayChangeRequirement($organizer, $requirementId);
        $this->commands->dispatch(new ChangeRequirement(
            $requirementId,
            $body->purpose,
            $body->confirmer,
            $body->mandatory,
            $body->revocable,
            $body->preChecked,
            $body->scopeText,
            $body->validity,
            $body->activityId,
            $body->variantId,
        ));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/admin/org/{orgSlug}/legal/requirements/{requirementId}', name: 'api_admin_legal_requirement_archive', methods: ['DELETE'], requirements: ['requirementId' => Requirement::UUID])]
    #[OA\Delete(
        summary: 'Stop applying a requirement to new registrations (the confirmations stay)',
        tags: ['legal'],
        responses: [
            new OA\Response(response: 204, description: 'Archived'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or legal_requirement_not_found'),
        ],
    )]
    public function archive(#[CurrentOrganizer] OrganizerView $organizer, string $requirementId): Response
    {
        $this->assertMayChangeRequirement($organizer, $requirementId);
        $this->commands->dispatch(new ArchiveRequirement($requirementId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/admin/org/{orgSlug}/legal/preview', name: 'api_admin_legal_preview', methods: ['GET'])]
    #[OA\Get(
        summary: 'What applies to a registration for an event (and one activity and variant): documents, versions, who confirms',
        tags: ['legal'],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'activityId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'variantId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'minor', in: 'query', description: 'Preview for a child (guardian requirements apply)', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: '{orderLevel: [...], perSlot: [...]}'), new OA\Response(response: 401, description: 'unauthorized'), new OA\Response(response: 403, description: 'forbidden'), new OA\Response(response: 404, description: 'not_found'), new OA\Response(response: 422, description: 'validation_failed')],
    )]
    public function preview(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $event = $request->query->getString('eventId');
        $activity = $request->query->getString('activityId');
        $variant = $request->query->getString('variantId');
        if (!Uuid::isValid($event) || ('' !== $activity && !Uuid::isValid($activity)) || ('' !== $variant && !Uuid::isValid($variant)) || ('' === $activity) !== ('' === $variant)) {
            throw new UnprocessableEntityHttpException('eventId is required; activityId and variantId must be given together and be UUIDs.');
        }

        $slots = '' === $activity ? [] : [new RegistrationSlot('preview', ActivityId::fromString($activity), VariantId::fromString($variant), $request->query->getBoolean('minor'))];
        $applicable = $this->resolver->forRegistration($organizer->id, EventId::fromString($event), $slots);
        $view = static fn(ApplicableRequirement $r): array => [
            'requirementId' => $r->requirementId->toString(),
            'documentId' => $r->documentId->toString(),
            'versionId' => $r->versionId->toString(),
            'documentTitle' => $r->documentTitle,
            'type' => $r->type->value,
            'purpose' => $r->purpose,
            'confirmer' => $r->confirmer->value,
            'mandatory' => $r->mandatory,
            'revocable' => $r->revocable,
        ];

        $perSlot = array_map(static fn(array $list): array => array_map($view, $list), $applicable->perSlot);

        return $this->json([
            'orderLevel' => array_map($view, $applicable->orderLevel),
            'perSlot' => [] === $perSlot ? new \stdClass() : $perSlot,
        ]);
    }

    private function assertMayChangeRequirement(OrganizerView $organizer, string $requirementId): void
    {
        $document = $this->requirements->documentOf($organizer->id, $requirementId);
        $this->guard->assertMayChange($organizer, $this->documents->levelOf($organizer->id, LegalDocumentId::fromString($document)));
    }
}
