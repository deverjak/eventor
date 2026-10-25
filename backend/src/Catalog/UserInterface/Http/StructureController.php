<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Eventor\Catalog\Application\Query\AdminEventQuery;
use Eventor\Catalog\Application\Structure\ActivityInput;
use Eventor\Catalog\Application\Structure\AddActivity;
use Eventor\Catalog\Application\Structure\AddVariant;
use Eventor\Catalog\Application\Structure\ChangeCapacity;
use Eventor\Catalog\Application\Structure\ChangeCapacityInput;
use Eventor\Catalog\Application\Structure\RemoveActivity;
use Eventor\Catalog\Application\Structure\RemoveVariant;
use Eventor\Catalog\Application\Structure\UpdateActivity;
use Eventor\Catalog\Application\Structure\UpdateVariant;
use Eventor\Catalog\Application\Structure\VariantInput;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Structure of an event: activities (EVT-05), variants with prices (EVT-06) and capacity limits (CAP-06). Every write
 * answers with the whole event, so the editor re-renders from one document.
 */
#[AsController]
final class StructureController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AdminEventQuery $events,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/activities', name: 'api_admin_activity_add', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Post(
        summary: 'Add an activity (a published event gets its capacity bucket at once)',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'The event with the new activity; `activityId` in the Location-less body is the new id'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function addActivity(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] ActivityInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        /** @var array{activityId: string} $result */
        $result = $this->commands->dispatch(new AddActivity($id, $input));

        return $this->json(['activityId' => $result['activityId'], 'event' => $this->events->detail($organizer->id, $id)], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}', name: 'api_admin_activity_update', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID, 'activityId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Put(
        summary: 'Replace the data of an activity; moving the start of a published activity emits activity_rescheduled',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'activityId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'If-Match', in: 'header', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / activity_not_found'),
            new OA\Response(response: 409, description: 'version_conflict'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function updateActivity(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $activityId, Request $request, #[MapRequestPayload] ActivityInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new UpdateActivity($id, ActivityId::fromString($activityId), $input, ExpectedVersion::from($request)));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}', name: 'api_admin_activity_remove', methods: ['DELETE'], requirements: ['eventId' => Requirement::UUID, 'activityId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Delete(
        summary: 'Remove an activity with its variants (draft events only)',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'activityId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / activity_not_found'),
            new OA\Response(response: 409, description: 'event_not_draft'),
        ],
    )]
    public function removeActivity(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $activityId): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new RemoveActivity($id, ActivityId::fromString($activityId)));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}/variants', name: 'api_admin_variant_add', methods: ['POST'], requirements: ['eventId' => Requirement::UUID, 'activityId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Post(
        summary: 'Add a variant (price level) to an activity',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'activityId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'variantId and the updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / activity_not_found'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function addVariant(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $activityId, #[MapRequestPayload] VariantInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        /** @var array{variantId: string} $result */
        $result = $this->commands->dispatch(new AddVariant($id, ActivityId::fromString($activityId), $input));

        return $this->json(['variantId' => $result['variantId'], 'event' => $this->events->detail($organizer->id, $id)], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/variants/{variantId}', name: 'api_admin_variant_update', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID, 'variantId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Put(
        summary: 'Replace the data of a variant; a price change of a published event gives the price list a new version',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'variantId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'If-Match', in: 'header', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / variant_not_found'),
            new OA\Response(response: 409, description: 'version_conflict'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function updateVariant(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $variantId, Request $request, #[MapRequestPayload] VariantInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new UpdateVariant($id, VariantId::fromString($variantId), $input, ExpectedVersion::from($request)));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/variants/{variantId}', name: 'api_admin_variant_remove', methods: ['DELETE'], requirements: ['eventId' => Requirement::UUID, 'variantId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Delete(
        summary: 'Remove a variant (draft events only)',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'variantId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / variant_not_found'),
            new OA\Response(response: 409, description: 'event_not_draft'),
        ],
    )]
    public function removeVariant(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $variantId): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new RemoveVariant($id, VariantId::fromString($variantId)));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/capacity', name: 'api_admin_capacity_change', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Set the capacity limit of the event, an activity or a variant (CAP-06; a published event needs a reason)',
        tags: ['activities'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'belowOccupancy, warnings (`capacity_below_occupancy`) and the updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / activity_not_found / variant_not_found'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function changeCapacity(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] ChangeCapacityInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        /** @var array{belowOccupancy: bool, warnings: list<string>} $result */
        $result = $this->commands->dispatch(new ChangeCapacity($id, $input));

        return $this->json($result + ['event' => $this->events->detail($organizer->id, $id)]);
    }
}
