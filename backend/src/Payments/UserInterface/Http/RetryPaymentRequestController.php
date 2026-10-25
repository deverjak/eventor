<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\PaymentRequest\PaymentRequestIssuer;
use Eventor\Payments\Application\PaymentRequest\RetryPaymentRequest;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Zkusit znovu" on a payment request whose creation failed for good (S08, ADM-03): FAILED -> PENDING, then Pairrot is called.
 */
#[AsController]
final class RetryPaymentRequestController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly PaymentRequestIssuer $issuer,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/payment-requests/{paymentRequestId}/retry', name: 'api_admin_payments_retry_request', methods: ['POST'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Post(
        summary: 'Retry creating a failed payment request',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'paymentRequestId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: '{paymentRequestId}: the request was created in Pairrot'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / payment_request_not_found'),
            new OA\Response(response: 409, description: 'payment_request_state: the request has not failed'),
            new OA\Response(response: 503, description: 'pairrot_unavailable: it stays PENDING and the outbox-style retry is manual'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $paymentRequestId): JsonResponse
    {
        $id = $this->routeId($paymentRequestId, PaymentRequestId::class);
        $this->commands->dispatch(new RetryPaymentRequest($id->toString()));

        try {
            $this->issuer->issue($id);
        } catch (PaymentGatewayUnavailable $e) {
            throw PairrotUnavailable::because($e->errorCode());
        }

        return $this->json(['paymentRequestId' => $id->toString()]);
    }
}
