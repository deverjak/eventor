<?php

declare(strict_types=1);

namespace Eventor\CheckIn\UserInterface\Http;

use Eventor\CheckIn\Application\CorrectCheckIn\CorrectCheckIn;
use Eventor\CheckIn\Application\VoidCheckIn\VoidCheckIn;
use Eventor\CheckIn\UserInterface\Http\Request\CorrectCheckInRequest;
use Eventor\CheckIn\UserInterface\Http\Request\VoidCheckInRequest;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInActor;
use Eventor\CheckIn\UserInterface\Http\ValueResolver\CheckInOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * CHK-07: a manager voids or corrects a check-in. Audited with a mandatory reason; devices have no such permission.
 */
#[AsController]
final class CheckInCorrectionController extends AbstractController
{
    public function __construct(private readonly CommandBus $commands) {}

    #[Route(CheckInRoutes::TEAM.'/check-ins/{checkInId}/void', name: 'api_check_in_void', requirements: ['eventId' => Requirement::UUID, 'checkInId' => Requirement::UUID], methods: ['POST'])]
    #[RequiresPermission(Permission::CheckInCorrect)]
    #[OA\Post(
        summary: 'Void a check-in (the participant can be checked in again)',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'checkInId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [
            new OA\Property(property: 'reason', type: 'string', maxLength: 500),
        ])),
        responses: [
            new OA\Response(response: 204, description: 'Voided'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / check_in_not_found'),
            new OA\Response(response: 409, description: 'check_in_already_voided'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function void(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $checkInId, #[MapRequestPayload] VoidCheckInRequest $body): Response
    {
        $this->commands->dispatch(new VoidCheckIn($eventId, $checkInId, $body->reason, $actor->accountId ?? throw new AccessDeniedException('Only a team member can void a check-in.')));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(CheckInRoutes::TEAM.'/check-ins/{checkInId}/correct', name: 'api_check_in_correct', requirements: ['eventId' => Requirement::UUID, 'checkInId' => Requirement::UUID], methods: ['POST'])]
    #[RequiresPermission(Permission::CheckInCorrect)]
    #[OA\Post(
        summary: 'Correct a check-in: the row is voided and a new valid one with the corrected station / time takes over',
        tags: ['check-in'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'checkInId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [
            new OA\Property(property: 'reason', type: 'string', maxLength: 500),
            new OA\Property(property: 'stationId', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'checkedInAt', type: 'string', format: 'date-time', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: '{checkInId, replacedCheckInId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / check_in_not_found'),
            new OA\Response(response: 409, description: 'check_in_already_voided'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function correct(#[CheckInOrganizer] OrganizerView $organizer, CheckInActor $actor, string $eventId, string $checkInId, #[MapRequestPayload] CorrectCheckInRequest $body): JsonResponse
    {
        $result = $this->commands->dispatch(new CorrectCheckIn($eventId, $checkInId, $body->reason, $body->stationId, $body->checkedInAt, $actor->accountId ?? throw new AccessDeniedException('Only a team member can correct a check-in.')));

        return $this->json($result);
    }
}
