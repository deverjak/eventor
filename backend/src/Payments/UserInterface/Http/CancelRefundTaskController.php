<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Refund\CancelRefundTask;
use Eventor\Payments\UserInterface\Http\Request\ReasonRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\RefundTaskId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class CancelRefundTaskController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly CommandBus $commands) {}

    #[Route('/api/admin/org/{orgSlug}/refund-tasks/{taskId}/cancel', name: 'api_admin_payments_cancel_refund_task', methods: ['POST'])]
    #[RequiresPermission(Permission::RefundClose)]
    #[OA\Post(
        summary: 'Cancel an open refund task (no money is returned)',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string')])),
        responses: [
            new OA\Response(response: 200, description: '{refundTaskId, state}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / refund_task_not_found'),
            new OA\Response(response: 409, description: 'refund_task_not_open'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $taskId, #[MapRequestPayload] ReasonRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new CancelRefundTask($this->routeId($taskId, RefundTaskId::class)->toString(), $body->reason)));
    }
}
