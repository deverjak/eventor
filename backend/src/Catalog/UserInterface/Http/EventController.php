<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Eventor\Catalog\Application\Event\ConfigurePaymentRules;
use Eventor\Catalog\Application\Event\CreateEvent;
use Eventor\Catalog\Application\Event\CreateEventInput;
use Eventor\Catalog\Application\Event\EventDetailsInput;
use Eventor\Catalog\Application\Event\PaymentRulesInput;
use Eventor\Catalog\Application\Event\UpdateEvent;
use Eventor\Catalog\Application\Query\AdminEventQuery;
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
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Events of the organizer (EVT-01, EVT-02, EVT-04 dates, EVT-10, EVT-11). Writes are commands, reads are the
 * DBAL query service; the organizer comes from the URL (ADR 005).
 */
#[AsController]
final class EventController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AdminEventQuery $events,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/events', name: 'api_admin_event_create', methods: ['POST'])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Post(
        summary: 'Create a draft event (the "simple" mode adds the implicit activity and variant)',
        tags: ['events'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 201, description: 'Created: eventId, slug, activityId and variantId of the simple mode'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
            new OA\Response(response: 409, description: 'event_slug_taken'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function create(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] CreateEventInput $input): JsonResponse
    {
        return $this->json($this->commands->dispatch(new CreateEvent($organizer->id, $input)), 201);
    }

    #[Route('/api/admin/org/{orgSlug}/events', name: 'api_admin_event_list', methods: ['GET'])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'Events of the organizer, newest first',
        tags: ['events'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'List of events'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json(['items' => $this->events->list($organizer->id)]);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}', name: 'api_admin_event_detail', methods: ['GET'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'One event with its activities and variants (draft included)',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The event; `version` goes into If-Match of the next write'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
        ],
    )]
    public function detail(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json($this->events->detail($organizer->id, EventId::fromString($eventId)));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}', name: 'api_admin_event_update', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Put(
        summary: 'Replace the descriptive data of an event (optimistic lock with If-Match: <version>)',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'If-Match', in: 'header', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.edit'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'version_conflict'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function update(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, Request $request, #[MapRequestPayload] EventDetailsInput $details): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new UpdateEvent($id, $details, ExpectedVersion::from($request)));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/payment-rules', name: 'api_admin_event_payment_rules', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Put(
        summary: 'Payment and cancellation rules of the event (EVT-10)',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'If-Match', in: 'header', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The updated event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'version_conflict'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function paymentRules(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, Request $request, #[MapRequestPayload] PaymentRulesInput $rules): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new ConfigurePaymentRules($id, $rules, ExpectedVersion::from($request)));

        return $this->json($this->events->detail($organizer->id, $id));
    }
}
