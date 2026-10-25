<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Unmatched\UnmatchedAllocator;
use Eventor\Payments\UserInterface\Http\Request\AllocateRequest;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-06: finance allocates an unmatched transaction to an order. Pairrot is asked to allocate; the ledger changes when
 * the resulting `payment_request.updated` comes back through the inbox, so the answer is 202.
 */
#[AsController]
final class AllocateUnmatchedTransactionController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly UnmatchedAllocator $allocator) {}

    #[Route('/api/admin/org/{orgSlug}/unmatched-transactions/{transactionId}/allocate', name: 'api_admin_payments_allocate_unmatched', methods: ['POST'])]
    #[RequiresPermission(Permission::PaymentExceptionResolve)]
    #[OA\Post(
        summary: 'Allocate an unmatched bank transaction to an order (asks Pairrot; the money arrives via the inbox)',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['orderId', 'reason'], properties: [
            new OA\Property(property: 'orderId', type: 'string', format: 'uuid'),
            new OA\Property(property: 'reason', type: 'string'),
            new OA\Property(property: 'paymentRequestId', type: 'string', format: 'uuid', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 202, description: '{paymentRequestId}: allocation requested'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / unmatched_transaction_not_found'),
            new OA\Response(response: 409, description: 'allocation_not_possible'),
            new OA\Response(response: 422, description: 'validation_failed'),
            new OA\Response(response: 503, description: 'pairrot_unavailable'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $transactionId, #[MapRequestPayload] AllocateRequest $body): JsonResponse
    {
        $result = $this->allocator->allocate(
            $organizer->id,
            $this->routeId($transactionId, BankTransactionId::class),
            OrderId::fromString($body->orderId),
            null === $body->paymentRequestId ? null : PaymentRequestId::fromString($body->paymentRequestId),
            $body->reason,
        );

        return $this->json($result, 202);
    }
}
