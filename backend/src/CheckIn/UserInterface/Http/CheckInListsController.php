<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http;

use Eventor\CheckIn\Application\Query\CheckInDeskQuery;
use Eventor\CheckIn\Application\Query\CheckInOverviewQuery;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInActor;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * CHK-01 and CHK-10: the check-in lists of an event and the live operational overview. A device token bound to one
 * list sees that list only.
 */
#[AsController]
final class CheckInListsController extends AbstractController
{
    public function __construct(
        private readonly CheckInDeskQuery $desk,
        private readonly CheckInOverviewQuery $overview,
    ) {}

    #[Route(CheckInRoutes::TEAM.'/lists', name: 'api_check_in_lists', requirements: ['eventId' => Requirement::UUID], methods: ['GET'])]
    #[Route(CheckInRoutes::DEVICE.'/lists', name: 'api_check_in_lists_device', requirements: ['eventId' => Requirement::UUID], methods: ['GET'])]
    #[RequiresPermission(Permission::ParticipantsViewCheckIn)]
    #[OA\Get(
        summary: 'Check-in lists of an event (one per activity) with expected and arrived counters',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{id, eventId, activityId, name, opensAt, expected, checkedIn}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function lists(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId): JsonResponse
    {
        return $this->json(['items' => $this->desk->lists($organizer->id, EventId::fromString($eventId), $actor->deviceList)]);
    }

    #[Route(CheckInRoutes::TEAM.'/overview', name: 'api_check_in_overview', requirements: ['eventId' => Requirement::UUID], methods: ['GET'])]
    #[Route(CheckInRoutes::DEVICE.'/overview', name: 'api_check_in_overview_device', requirements: ['eventId' => Requirement::UUID], methods: ['GET'])]
    #[RequiresPermission(Permission::ParticipantsViewCheckIn)]
    #[OA\Get(
        summary: 'Operational overview of the event: expected / arrived / missing / unpaid / cash received, per activity',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: false, description: 'Team members only; a device token needs none', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: '{eventId, lists: [{listId, activityId, name, opensAt, expected, checkedIn, missing, unpaid, cashReceivedMinor}], expected, checkedIn, missing, unpaid, cashReceivedMinor}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function overview(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId): JsonResponse
    {
        return $this->json($this->overview->forEvent($organizer->id, EventId::fromString($eventId), $actor->deviceList));
    }
}
