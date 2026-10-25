<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\CorrectCashEntry\CorrectCashEntry;
use Eventor\Payments\UserInterface\Http\Request\CorrectCashRequest;
use Eventor\Shared\Application\Idempotency\IdempotencyKey;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\IdempotencyStamp;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * S11 / PAY-08: correct a cash entry with a compensating entry (and, optionally, a new cash entry with the right
 * amount). Both entries stay in the ledger; the reason is mandatory.
 */
#[AsController]
final class CorrectCashEntryController extends AbstractController
{
    use ParsesRouteIds;

    public function __construct(private readonly CommandBus $commands) {}

    #[Route('/api/admin/org/{orgSlug}/orders/{orderId}/ledger/{entryId}/correction', name: 'api_admin_payments_correct_cash', methods: ['POST'])]
    #[RequiresPermission(Permission::CashCorrect)]
    #[OA\Post(
        summary: 'Correct a cash ledger entry (compensating entry with a reason)',
        tags: ['payments'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'entryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [
            new OA\Property(property: 'reason', type: 'string'),
            new OA\Property(property: 'replacementAmountMinor', type: 'integer', nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: '{correctionId, replacementId, alreadyRecorded, state, received, amountDue}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / ledger_entry_not_found'),
            new OA\Response(response: 409, description: 'ledger_entry_not_correctable'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $orderId, string $entryId, #[MapRequestPayload] CorrectCashRequest $body, IdempotencyKey $key): JsonResponse
    {
        $order = $this->routeId($orderId, OrderId::class);
        $entry = $this->routeId($entryId, LedgerEntryId::class);
        $result = $this->commands->dispatch(
            new CorrectCashEntry($order->toString(), $entry->toString(), $body->reason, $key->value, $body->replacementAmountMinor),
            IdempotencyStamp::for('payments.cash_correction:'.$organizer->id->toString().':'.$entry->toString(), $key->value, ['reason' => $body->reason, 'replacementAmountMinor' => $body->replacementAmountMinor]),
        );

        return $this->json($result);
    }
}
