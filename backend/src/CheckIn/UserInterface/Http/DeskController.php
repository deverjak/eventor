<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http;

use Eventor\CheckIn\Application\Query\CheckInDeskQuery;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInActor;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInOrganizer;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\ResolvedParticipation;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Contract\Security\EventPermissionSubject;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The reading side of the desk: search (CHK-02) and the detail of a participant with the verdict (CHK-04, CHK-09).
 */
#[AsController]
final class DeskController extends AbstractController
{
    public function __construct(private readonly CheckInDeskQuery $desk) {}

    #[Route(CheckInRoutes::TEAM.'/lists/{listId}/participants', name: 'api_check_in_search', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['GET'])]
    #[Route(CheckInRoutes::DEVICE.'/lists/{listId}/participants', name: 'api_check_in_search_device', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['GET'])]
    #[RequiresPermission(Permission::ParticipantsViewCheckIn)]
    #[OA\Get(
        summary: 'Search the participants of a check-in list by name or registration code',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'listId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'q', in: 'query', required: true, description: 'At least two characters', schema: new OA\Schema(type: 'string', minLength: 2)),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{participationId, displayName, registrationCode, activityId, state, checkedInAt}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / check_in_list_not_found'),
            new OA\Response(response: 409, description: 'check_in_list_closed'),
        ],
    )]
    public function search(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $listId, Request $request): JsonResponse
    {
        $list = CheckInListId::fromString($listId);
        $actor->assertMayUse($list);
        $term = trim($request->query->getString('q'));
        if (mb_strlen($term) < 2) {
            return $this->json(['items' => []]);
        }

        return $this->json(['items' => $this->desk->search($organizer->id, EventId::fromString($eventId), $list, $term)]);
    }

    #[Route(CheckInRoutes::TEAM.'/lists/{listId}/lookup', name: 'api_check_in_lookup', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[Route(CheckInRoutes::DEVICE.'/lists/{listId}/lookup', name: 'api_check_in_lookup_device', requirements: ['eventId' => Requirement::UUID, 'listId' => Requirement::UUID], methods: ['POST'])]
    #[RequiresPermission(Permission::ParticipantsViewCheckIn)]
    #[OA\Post(
        summary: 'Look a participant up by QR token, registration code or id: the check-in detail and the verdict of the exception matrix (writes nothing)',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'listId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'qr', type: 'string', description: 'qr1.<payload>.<sig>'),
            new OA\Property(property: 'code', type: 'string', description: 'Registration code'),
            new OA\Property(property: 'participationId', type: 'string', format: 'uuid'),
        ], description: 'Exactly one of the three')),
        responses: [
            new OA\Response(response: 200, description: 'The participant with `decision` (allow | warn | escalate | block), `situation`, `checkIn` (when already checked in) and `cash` (the cash offer of an unpaid participant)'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / participation_not_found / check_in_list_not_found'),
            new OA\Response(response: 409, description: 'check_in_list_closed'),
            new OA\Response(response: 422, description: 'validation_failed / qr_token_invalid'),
        ],
    )]
    public function lookup(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $listId, ResolvedParticipation $participation): JsonResponse
    {
        $list = CheckInListId::fromString($listId);
        $actor->assertMayUse($list);
        $event = EventId::fromString($eventId);
        $mayRecordCash = $this->isGranted(Permission::CashRecord->value, new EventPermissionSubject($organizer, $event));

        return $this->json($this->desk->participant($organizer->id, $event, $list, $participation->id, $mayRecordCash));
    }
}
