<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Registration\Application\Cancellation\CancelParticipation;
use Eventor\Registration\Application\Query\AdminParticipationQuery;
use Eventor\Registration\Application\Query\PreviewSelection;
use Eventor\Registration\Application\Query\RegistrationPreviewQuery;
use Eventor\Registration\Application\Query\View\ParticipationRowView;
use Eventor\Registration\Application\Restore\RestoreParticipation;
use Eventor\Registration\Contract\CancelledBy;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\RestoredBy;
use Eventor\Registration\UserInterface\Http\Request\Payload;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\VariantId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The organizer's view of registrations: participants and orders of an event, the cancellation by the organizer (REG-14),
 * the restore action of CAP-05 and the form preview (FRM-11). Order of failures: 401, 404 (foreign organizer), 403 (role).
 */
#[AsController]
final class AdminRegistrationController extends AbstractController
{
    private const string ORG = '/api/admin/org/{orgSlug}';

    public function __construct(
        private readonly AdminParticipationQuery $participations,
        private readonly CommandBus $commands,
        private readonly RegistrationPreviewQuery $preview,
    ) {}

    #[Route(self::ORG.'/events/{eventId}/participations', name: 'api_admin_participation_list', methods: ['GET'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ParticipantsViewFull)]
    #[OA\Get(
        summary: 'Participants of an event with the four dimensions of each participation (state, legal, answers, finance)',
        tags: ['registration'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'state', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'confirmed', 'expired', 'requires_attention', 'cancelled', 'transferred'])),
            new OA\Parameter(name: 'activityId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'q', in: 'query', description: 'name, e-mail, registration code or order number', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 50, maximum: 200)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'items, total, page, limit'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found (unknown organizer or not a member)'),
            new OA\Response(response: 422, description: 'invalid_order_request (bad filter)'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, Request $request): JsonResponse
    {
        $state = $request->query->getString('state');
        $activity = $request->query->getString('activityId');
        if (('' !== $state && null === ParticipationState::tryFrom($state)) || ('' !== $activity && !ActivityId::isValid($activity))) {
            throw InvalidOrderRequest::at('state', 'invalid_filter', 'Unknown state or activity filter.');
        }

        return $this->json($this->participations->list(
            $organizer->id,
            EventId::fromString($eventId),
            '' === $state ? null : ParticipationState::from($state),
            '' === $activity ? null : ActivityId::fromString($activity),
            $request->query->getString('q'),
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 50),
        ));
    }

    #[Route(self::ORG.'/events/{eventId}/participations/{participationId}', name: 'api_admin_participation_detail', methods: ['GET'], requirements: ['eventId' => Requirement::UUID, 'participationId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ParticipantsViewFull)]
    #[OA\Get(
        summary: 'One participation',
        tags: ['registration'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'participationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The participation'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found'),
        ],
    )]
    public function detail(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $participationId): JsonResponse
    {
        return $this->json($this->inEvent($organizer, $eventId, $participationId));
    }

    #[Route(self::ORG.'/events/{eventId}/orders/{orderId}', name: 'api_admin_order_detail', methods: ['GET'], requirements: ['eventId' => Requirement::UUID, 'orderId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ParticipantsViewFull)]
    #[OA\Get(
        summary: 'An order with its participations and the financial state',
        tags: ['registration'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The order'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / order_not_found'),
        ],
    )]
    public function order(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $orderId): JsonResponse
    {
        $view = $this->participations->order($organizer->id, OrderId::fromString($orderId));
        if (!$view->eventId->equals(EventId::fromString($eventId))) {
            throw OrderNotFound::forId($view->id);
        }

        return $this->json($view);
    }

    #[Route(self::ORG.'/events/{eventId}/participations/{participationId}/cancel', name: 'api_admin_participation_cancel', methods: ['POST'], requirements: ['eventId' => Requirement::UUID, 'participationId' => Requirement::UUID])]
    #[RequiresPermission(Permission::RegistrationEdit)]
    #[OA\Post(
        summary: 'Cancel a participation as the organizer (REG-14); the reason is mandatory. Seats go back, the amount due drops, Payments opens a refund task when money was paid.',
        tags: ['registration'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'participationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')])),
        responses: [
            new OA\Response(response: 200, description: 'The cancelled participation'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found'),
            new OA\Response(response: 409, description: 'participation_transition_not_allowed'),
            new OA\Response(response: 422, description: 'invalid_order_request (reason_required)'),
        ],
    )]
    public function cancel(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $participationId, Request $request): JsonResponse
    {
        $this->inEvent($organizer, $eventId, $participationId);
        $id = ParticipationId::fromString($participationId);
        $this->commands->dispatch(new CancelParticipation($organizer->id, $id, CancelledBy::Organizer, $this->reason($request)));

        return $this->json($this->participations->participation($organizer->id, $id));
    }

    #[Route(self::ORG.'/events/{eventId}/participations/{participationId}/restore', name: 'api_admin_participation_restore', methods: ['POST'], requirements: ['eventId' => Requirement::UUID, 'participationId' => Requirement::UUID])]
    #[RequiresPermission(Permission::PaymentExceptionResolve)]
    #[OA\Post(
        summary: 'Restore a participation flagged after a late payment, if there is room (CAP-05). The reason is mandatory.',
        tags: ['registration'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'participationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')])),
        responses: [
            new OA\Response(response: 200, description: 'The restored participation'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found'),
            new OA\Response(response: 409, description: 'participation_transition_not_allowed (also: the capacity is full again, blocker capacity_exhausted)'),
            new OA\Response(response: 422, description: 'invalid_order_request (reason_required)'),
        ],
    )]
    public function restore(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $participationId, Request $request): JsonResponse
    {
        $this->inEvent($organizer, $eventId, $participationId);
        $id = ParticipationId::fromString($participationId);
        $this->commands->dispatch(new RestoreParticipation($organizer->id, $id, RestoredBy::User, $this->reason($request)));

        return $this->json($this->participations->participation($organizer->id, $id));
    }

    #[Route(self::ORG.'/events/{eventId}/form-preview', name: 'api_admin_form_preview', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::EventEdit)]
    #[OA\Post(
        summary: 'Preview of the registration form for a chosen combination of activities and variants (FRM-11): fields per person and for the order contact, legal documents to confirm and by whom. Works on the published form version.',
        tags: ['registration'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['items'], properties: [new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object'))])),
        responses: [
            new OA\Response(response: 200, description: 'formVersionId, orderFields, orderRequirements, items[]'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / event_not_found (an unpublished event has no form version)'),
            new OA\Response(response: 422, description: 'invalid_order_request'),
        ],
    )]
    public function formPreview(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, Request $request): JsonResponse
    {
        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            throw InvalidOrderRequest::at('body', 'invalid_json', 'The request body must be a JSON object.');
        }
        $selection = [];
        foreach (Payload::objects($body, 'items') as $i => $item) {
            $path = 'items.'.$i.'.';
            $selection[] = new PreviewSelection(
                Payload::string($item, 'itemRef', $path, false) ?? 'item'.$i,
                ActivityId::fromString((string) Payload::uuid($item, 'activityId', $path)),
                VariantId::fromString((string) Payload::uuid($item, 'variantId', $path)),
                true === ($item['minor'] ?? false),
            );
        }

        return $this->json($this->preview->preview($organizer->id, EventId::fromString($eventId), $selection, false));
    }

    /** The participation must belong to the event of the URL (the permission and the event scope were judged for that event). */
    private function inEvent(OrganizerView $organizer, string $eventId, string $participationId): ParticipationRowView
    {
        $row = $this->participations->participation($organizer->id, ParticipationId::fromString($participationId));

        return $row->eventId->equals(EventId::fromString($eventId)) ? $row : throw ParticipationNotFound::forId($row->id);
    }

    private function reason(Request $request): ?string
    {
        try {
            $reason = $request->toArray()['reason'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        return \is_string($reason) ? mb_substr($reason, 0, 500) : null;
    }
}
