<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Eventor\Catalog\Application\Publication\ArchiveEvent;
use Eventor\Catalog\Application\Publication\CancelEvent;
use Eventor\Catalog\Application\Publication\CancelEventInput;
use Eventor\Catalog\Application\Publication\CompleteEvent;
use Eventor\Catalog\Application\Publication\PublishEvent;
use Eventor\Catalog\Application\Query\AdminEventQuery;
use Eventor\Catalog\Application\Registration\ChangeRegistrationState;
use Eventor\Catalog\Application\Registration\RegistrationChangeInput;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Transitions of the two state machines of an event (chapter 05): publication (EVT-03) and registration window
 * (EVT-04). The state machines and their guards decide; a refused transition is a 409 `transition_not_allowed`.
 */
#[AsController]
final class EventLifecycleController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AdminEventQuery $events,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/publish', name: 'api_admin_event_publish', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Publish a draft event: rules check, immutable form version 1, capacity buckets',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Published: formVersionId and formVersion'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'transition_not_allowed: not a draft'),
            new OA\Response(response: 422, description: 'event_not_publishable: `violations` lists every broken rule'),
        ],
    )]
    public function publish(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new PublishEvent(EventId::fromString($eventId))));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/cancel', name: 'api_admin_event_cancel', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventCancel)]
    #[OA\Post(
        summary: 'Cancel a published event (owner): closes all registrations, starts the cancellation process',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The cancelled event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.cancel'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'transition_not_allowed'),
            new OA\Response(response: 422, description: 'validation_failed: reason and confirmation are required'),
        ],
    )]
    public function cancel(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] CancelEventInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new CancelEvent($id, $input->reason));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/complete', name: 'api_admin_event_complete', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Complete a published event after it has ended',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The completed event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'transition_not_allowed: not published, or not over yet'),
        ],
    )]
    public function complete(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new CompleteEvent($id));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/archive', name: 'api_admin_event_archive', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Archive a completed or cancelled event',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The archived event'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'transition_not_allowed'),
        ],
    )]
    public function archive(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new ArchiveEvent($id));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/registration/{transition}', name: 'api_admin_event_registration', methods: ['POST'], requirements: ['eventId' => Requirement::UUID, 'transition' => 'open|pause|resume|close'])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Open, pause, resume or close the registration of the event (`cascade` also moves the activities)',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'transition', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['open', 'pause', 'resume', 'close'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The event with the new registration state'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 409, description: 'transition_not_allowed'),
        ],
    )]
    public function eventRegistration(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $transition, #[MapRequestPayload] ?RegistrationChangeInput $input = null): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $input ??= new RegistrationChangeInput();
        $this->commands->dispatch(new ChangeRegistrationState($id, null, $transition, $input->reason, $input->cascade));

        return $this->json($this->events->detail($organizer->id, $id));
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/activities/{activityId}/registration/{transition}', name: 'api_admin_activity_registration', methods: ['POST'], requirements: ['eventId' => Requirement::UUID, 'activityId' => Requirement::UUID, 'transition' => 'open|pause|resume|close'])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Open, pause, resume or close the registration of one activity (opens only while the event registration is open)',
        tags: ['events'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'activityId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'transition', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['open', 'pause', 'resume', 'close'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The event with the new state of the activity'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / activity_not_found'),
            new OA\Response(response: 409, description: 'transition_not_allowed'),
        ],
    )]
    public function activityRegistration(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $activityId, string $transition, #[MapRequestPayload] ?RegistrationChangeInput $input = null): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $input ??= new RegistrationChangeInput();
        $this->commands->dispatch(new ChangeRegistrationState($id, ActivityId::fromString($activityId), $transition, $input->reason, false));

        return $this->json($this->events->detail($organizer->id, $id));
    }
}
