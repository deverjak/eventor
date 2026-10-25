<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Refund\OpenRefundTask;
use Eventor\Payments\UserInterface\Http\Request\OpenRefundRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-10: ask for money to be returned (EVENT_MANAGER may create, only FINANCE / OWNER may close).
 */
#[AsController]
final class OpenRefundTaskController extends AbstractController
{
    public function __construct(private readonly CommandBus $commands) {}

    #[Route('/api/admin/org/{orgSlug}/refund-tasks', name: 'api_admin_payments_open_refund_task', methods: ['POST'])]
    #[RequiresPermission(Permission::RefundCreate)]
    #[OA\Post(
        summary: 'Open a refund task for an order',
        tags: ['payments'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['orderId', 'amountMinor', 'reason'], properties: [
            new OA\Property(property: 'orderId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'amountMinor', type: 'integer', minimum: 1),
            new OA\Property(property: 'reason', type: 'string', enum: ['cancellation', 'overpayment', 'event_cancelled', 'late_payment']),
            new OA\Property(property: 'note', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: '{refundTaskId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / order_finance_not_found'),
            new OA\Response(response: 409, description: 'refund_task_not_open: the order already has an open task'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] OpenRefundRequest $body): JsonResponse
    {
        $result = $this->commands->dispatch(new OpenRefundTask($body->orderId, $body->amountMinor, $body->reason, $body->note));

        return $this->json($result, 201);
    }
}
