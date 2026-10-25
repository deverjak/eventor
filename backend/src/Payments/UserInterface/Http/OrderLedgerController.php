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
 * The append-only ledger of an order, oldest first: every payment, correction and refund with its actor and reason.
 */
#[AsController]
final class OrderLedgerController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly OrderFinanceDetailQuery $query) {}

    #[Route('/api/admin/org/{orgSlug}/orders/{orderId}/ledger', name: 'api_admin_payments_order_ledger', methods: ['GET'])]
    #[RequiresPermission(Permission::ParticipantsViewFull)]
    #[OA\Get(
        summary: 'Ledger entries of an order',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{id, kind, amount, source, compensatesId, actor, reason, occurredAt, recordedAt}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / order_finance_not_found'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $orderId): JsonResponse
    {
        return $this->json(['items' => $this->query->ledger($organizer->id, $this->routeId($orderId, OrderId::class))]);
    }
}
