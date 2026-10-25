<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\RecordManualPayment\RecordManualPayment;
use Eventor\Payments\UserInterface\Http\Request\ManualPaymentRequest;
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
 * Finance books a payment that Pairrot does not know (or removes one): a `manual_correction` with a mandatory reason.
 */
#[AsController]
final class RecordManualPaymentController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly CommandBus $commands) {}

    #[Route('/api/admin/org/{orgSlug}/orders/{orderId}/manual-payments', name: 'api_admin_payments_manual_payment', methods: ['POST'])]
    #[RequiresPermission(Permission::PaymentExceptionResolve)]
    #[OA\Post(
        summary: 'Book a payment outside Pairrot (manual correction with a reason)',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['amountMinor', 'reason'], properties: [
            new OA\Property(property: 'amountMinor', type: 'integer', description: 'Signed amount in haléře, not zero'),
            new OA\Property(property: 'reason', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: '{ledgerEntryId, alreadyRecorded, state, received, amountDue}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / order_finance_not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $orderId, #[MapRequestPayload] ManualPaymentRequest $body, IdempotencyKey $key): JsonResponse
    {
        $order = $this->routeId($orderId, OrderId::class);
        $result = $this->commands->dispatch(
            new RecordManualPayment($order->toString(), $body->amountMinor, $body->reason, $key->value),
            IdempotencyStamp::for('payments.manual:'.$organizer->id->toString().':'.$order->toString(), $key->value, ['amountMinor' => $body->amountMinor, 'reason' => $body->reason]),
        );

        return $this->json($result);
    }
}
