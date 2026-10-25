<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Query\PaymentsAdminQuery;
use Eventor\Shared\Domain\Id\BankTransactionId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-06: the reconciliation queue (bank transactions Pairrot could not allocate) and the candidates for one of them.
 */
#[AsController]
final class UnmatchedTransactionsController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly PaymentsAdminQuery $query) {}

    #[Route('/api/admin/org/{orgSlug}/unmatched-transactions', name: 'api_admin_payments_unmatched_transactions', methods: ['GET'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Get(
        summary: 'Unmatched bank transactions (reconciliation queue)',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'all'], default: 'open')),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{id, amount, variableSymbol, bookedOn, matchingResult, resolvedAt, resolution}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        return $this->json(['items' => $this->query->unmatchedTransactions($organizer->id, 'all' !== $request->query->get('state'))]);
    }

    #[Route('/api/admin/org/{orgSlug}/unmatched-transactions/{transactionId}/candidates', name: 'api_admin_payments_unmatched_candidates', methods: ['GET'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Get(
        summary: 'Orders that could own an unmatched transaction (same variable symbol or amount)',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{paymentRequestId, orderId, variableSymbol, amount, outstanding, reasons[]}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / unmatched_transaction_not_found'),
        ],
    )]
    public function candidates(#[CurrentOrganizer] OrganizerView $organizer, string $transactionId): JsonResponse
    {
        $id = $this->routeId($transactionId, BankTransactionId::class);
        $items = $this->query->candidates($organizer->id, $id);

        return $this->json(['items' => $items]);
    }
}
