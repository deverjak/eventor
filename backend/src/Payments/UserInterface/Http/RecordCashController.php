<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\RecordCash\RecordCash;
use Eventor\Payments\UserInterface\Http\Request\RecordCashRequest;
use Eventor\Shared\Application\Idempotency\IdempotencyKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Shared\Domain\Id\OrderId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PAY-07: cash taken for an order (outside the check-in desk). Idempotent per `Idempotency-Key`.
 */
#[AsController]
final class RecordCashController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly CommandBus $commands) {}

    #[Route('/api/admin/org/{orgSlug}/orders/{orderId}/cash', name: 'api_admin_payments_record_cash', methods: ['POST'])]
    #[RequiresPermission(Permission::CashRecord)]
    #[OA\Post(
        summary: 'Record cash for an order',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['amountMinor'], properties: [
            new OA\Property(property: 'amountMinor', type: 'integer', minimum: 1, description: 'Amount in haléře'),
            new OA\Property(property: 'note', type: 'string', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: '{ledgerEntryId, alreadyRecorded, state, received, amountDue}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / order_finance_not_found'),
            new OA\Response(response: 409, description: 'idempotency_key_reused'),
            new OA\Response(response: 400, description: 'idempotency_key_required'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $orderId, #[MapRequestPayload] RecordCashRequest $body, IdempotencyKey $key): JsonResponse
    {
        $order = $this->routeId($orderId, OrderId::class);
        $result = $this->commands->dispatch(
            new RecordCash($order->toString(), $body->amountMinor, $key->value, $body->note),
            IdempotencyStamp::for('payments.cash:'.$organizer->id->toString().':'.$order->toString(), $key->value, ['amountMinor' => $body->amountMinor, 'note' => $body->note]),
        );

        return $this->json($result);
    }
}
