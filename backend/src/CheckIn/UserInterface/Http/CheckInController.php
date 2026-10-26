<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http;

use Eventor\CheckIn\Application\RaiseEscalation\RaiseEscalation;
use Eventor\CheckIn\Application\RecordCashAndCheckIn\RecordCashAndCheckIn;
use Eventor\CheckIn\Application\RecordCheckIn\RecordCheckIn;
use Eventor\CheckIn\UserInterface\Http\Request\CashCheckInRequest;
use Eventor\CheckIn\UserInterface\Http\Request\CheckInRequest;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInActor;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInOrganizer;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\ResolvedParticipation;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Contract\Security\EventPermissionSubject;
use Eventor\Shared\Application\Idempotency\IdempotencyKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The writing side of the desk: confirm an arrival (CHK-05, CHK-06), cash and check in in one step (CHK-08) and pass a
 * situation on to a manager (CHK-09). Both ways in: a team session and a device token (`Authorization: Bearer dev1_...`).
 */
#[AsController]
final class CheckInController extends AbstractController
{
    public function __construct(private readonly CommandBus $commands) {}

    #[Route(CheckInRoutes::TEAM.'/lists/{listId}/check-ins', name: 'api_check_in_record', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[Route(CheckInRoutes::DEVICE.'/lists/{listId}/check-ins', name: 'api_check_in_record_device', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[RequiresPermission(Permission::CheckInPerform)]
    #[OA\Post(
        summary: 'Check a participant in. The exception matrix decides: allow, warn (acknowledge it), escalate (202, the escalation is raised) or block (409)',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'listId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'qr', type: 'string', description: 'qr1.<payload>.<sig>'),
            new OA\Property(property: 'code', type: 'string', description: 'Registration code'),
            new OA\Property(property: 'participationId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'stationId', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'acknowledgeWarning', type: 'boolean', default: false),
        ], description: 'Exactly one of qr, code and participationId')),
        responses: [
            new OA\Response(response: 201, description: '{outcome: checked_in, checkInId, checkedInAt, situation}'),
            new OA\Response(response: 202, description: '{outcome: escalated, situation}: the situation was passed on, the participant is not checked in'),
            new OA\Response(response: 400, description: 'idempotency_key_required'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden (also a device token bound to another list)'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found / check_in_list_not_found'),
            new OA\Response(response: 409, description: 'already_checked_in (checkedInAt, checkedInBy, stationId, deviceLabel) / check_in_blocked / check_in_warning_unacknowledged / check_in_list_closed / idempotency_key_reused'),
            new OA\Response(response: 422, description: 'validation_failed / qr_token_invalid'),
        ],
    )]
    public function checkIn(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $listId, ResolvedParticipation $participation, #[MapRequestPayload] CheckInRequest $body, IdempotencyKey $key): JsonResponse
    {
        $list = CheckInListId::fromString($listId);
        $actor->assertMayUse($list);

        $result = $this->commands->dispatch(
            new RecordCheckIn($eventId, $list->toString(), $participation->id->toString(), $body->stationId, $body->acknowledgeWarning, $actor->accountId, $actor->deviceLabel),
            IdempotencyStamp::for('checkin.record:'.$organizer->id->toString().':'.$list->toString(), $key->value, [
                'participationId' => $participation->id->toString(),
                'stationId' => $body->stationId,
                'acknowledgeWarning' => $body->acknowledgeWarning,
            ]),
        );

        return $this->json($result, \is_array($result) && 'escalated' === ($result['outcome'] ?? null) ? 202 : 201);
    }

    #[Route(CheckInRoutes::TEAM.'/lists/{listId}/cash-check-ins', name: 'api_check_in_cash', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[Route(CheckInRoutes::DEVICE.'/lists/{listId}/cash-check-ins', name: 'api_check_in_cash_device', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[RequiresPermission(Permission::CashRecord)]
    #[OA\Post(
        summary: 'Take cash from an unpaid participant and check them in, in one transaction. A device needs the organizer policy `check_in_may_record_cash`',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'listId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['amountMinor'], properties: [
            new OA\Property(property: 'qr', type: 'string'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'participationId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'amountMinor', type: 'integer', minimum: 1, description: 'Amount in haléře'),
            new OA\Property(property: 'note', type: 'string', nullable: true),
            new OA\Property(property: 'stationId', type: 'string', format: 'uuid', nullable: true),
        ], description: 'Exactly one of qr, code and participationId')),
        responses: [
            new OA\Response(response: 201, description: '{checkInId, checkedInAt, ledgerEntryId, alreadyRecorded, financialState, received, amountDue}'),
            new OA\Response(response: 400, description: 'idempotency_key_required'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden / cash_not_allowed'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found / check_in_list_not_found'),
            new OA\Response(response: 409, description: 'already_checked_in / check_in_blocked / cash_check_in_not_applicable / check_in_list_closed / idempotency_key_reused'),
            new OA\Response(response: 422, description: 'validation_failed / qr_token_invalid'),
        ],
    )]
    public function cash(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $listId, ResolvedParticipation $participation, #[MapRequestPayload] CashCheckInRequest $body, IdempotencyKey $key): JsonResponse
    {
        $list = CheckInListId::fromString($listId);
        $actor->assertMayUse($list);
        // One permission can sit on the route; the other is checked here (a finance clerk takes cash but does not check people in).
        $this->denyAccessUnlessGranted(Permission::CheckInPerform->value, new EventPermissionSubject($organizer, EventId::fromString($eventId)));

        $result = $this->commands->dispatch(
            new RecordCashAndCheckIn($eventId, $list->toString(), $participation->id->toString(), $body->amountMinor, $body->stationId, $body->note, $key->value, $actor->accountId, $actor->deviceLabel),
            IdempotencyStamp::for('checkin.cash:'.$organizer->id->toString().':'.$list->toString(), $key->value, [
                'participationId' => $participation->id->toString(),
                'amountMinor' => $body->amountMinor,
                'note' => $body->note,
                'stationId' => $body->stationId,
            ]),
        );

        return $this->json($result, 201);
    }

    #[Route(CheckInRoutes::TEAM.'/lists/{listId}/escalations', name: 'api_check_in_escalate', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[Route(CheckInRoutes::DEVICE.'/lists/{listId}/escalations', name: 'api_check_in_escalate_device', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[RequiresPermission(Permission::CheckInPerform)]
    #[OA\Post(
        summary: 'Pass the situation of a participant on to a manager (raises checkin.escalation_raised; Attention opens an item)',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'listId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'qr', type: 'string'),
            new OA\Property(property: 'code', type: 'string'),
            new OA\Property(property: 'participationId', type: 'string', format: 'uuid'),
        ], description: 'Exactly one of the three')),
        responses: [
            new OA\Response(response: 201, description: '{situation}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found / check_in_list_not_found'),
            new OA\Response(response: 409, description: 'nothing_to_escalate / check_in_list_closed'),
            new OA\Response(response: 422, description: 'validation_failed / qr_token_invalid'),
        ],
    )]
    public function escalate(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $listId, ResolvedParticipation $participation): JsonResponse
    {
        $list = CheckInListId::fromString($listId);
        $actor->assertMayUse($list);

        return $this->json($this->commands->dispatch(new RaiseEscalation($eventId, $list->toString(), $participation->id->toString())), 201);
    }
}
