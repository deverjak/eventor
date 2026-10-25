<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Query\OrderFinanceDetailQuery;
use Eventor\Shared\Domain\Id\OrderId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Financial picture of one order: state, split transfer / cash / refunded (PAY-13), payment requests, open refund task.
 */
#[AsController]
final class OrderFinanceController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly OrderFinanceDetailQuery $query) {}

    #[Route('/api/admin/org/{orgSlug}/orders/{orderId}/finance', name: 'api_admin_payments_order_finance', methods: ['GET'])]
    #[RequiresPermission(Permission::ParticipantsViewFull)]
    #[OA\Get(
        summary: 'Financial state of an order with its payment requests',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'state, amountDue, received (transfer/cash), refunded, outstanding, paymentRequests[], openRefundTask'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / order_finance_not_found (also for an order of another organizer)'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $orderId): JsonResponse
    {
        return $this->json($this->query->detail($organizer->id, $this->routeId($orderId, OrderId::class)));
    }
}
