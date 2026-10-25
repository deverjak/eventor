<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Refund\SettleRefundTask;
use Eventor\Payments\UserInterface\Http\Request\SettleRefundRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\RefundTaskId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-11: the money was returned outside the system; the task is settled and a `refund_settled` entry is written.
 */
#[AsController]
final class SettleRefundTaskController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly CommandBus $commands) {}

    #[Route('/api/admin/org/{orgSlug}/refund-tasks/{taskId}/settle', name: 'api_admin_payments_settle_refund_task', methods: ['POST'])]
    #[RequiresPermission(Permission::RefundClose)]
    #[OA\Post(
        summary: 'Settle a refund task',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['method'], properties: [
            new OA\Property(property: 'method', type: 'string', example: 'bank_transfer'),
            new OA\Property(property: 'reference', type: 'string', nullable: true),
            new OA\Property(property: 'note', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: '{refundTaskId, ledgerEntryId, state}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: EVENT_MANAGER may create but not close'),
            new OA\Response(response: 404, description: 'not_found / refund_task_not_found'),
            new OA\Response(response: 409, description: 'refund_task_not_open'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $taskId, #[MapRequestPayload] SettleRefundRequest $body): JsonResponse
    {
        $task = $this->routeId($taskId, RefundTaskId::class);

        return $this->json($this->commands->dispatch(new SettleRefundTask($task->toString(), $body->method, $body->reference, $body->note)));
    }
}
