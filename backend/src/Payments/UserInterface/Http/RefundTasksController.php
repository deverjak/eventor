<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Query\PaymentsAdminQuery;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-10 / PAY-11: the refund tasks of the organizer (the manual refund queue), newest first.
 */
#[AsController]
final class RefundTasksController extends AbstractController
{
    public function __construct(private readonly PaymentsAdminQuery $query) {}

    #[Route('/api/admin/org/{orgSlug}/refund-tasks', name: 'api_admin_payments_refund_tasks', methods: ['GET'])]
    #[RequiresPermission(Permission::RefundCreate)]
    #[OA\Get(
        summary: 'List refund tasks',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'settled', 'cancelled'])),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{id, orderId, amount, reason, status, note, createdAt, settledAt, settleMethod, settleReference}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed: unknown status'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        if (null !== $status && !\in_array($status, ['open', 'settled', 'cancelled'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException('status must be open, settled or cancelled.');
        }

        return $this->json(['items' => $this->query->refundTasks($organizer->id, \is_string($status) ? $status : null)]);
    }
}
