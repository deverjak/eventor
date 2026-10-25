<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Payments\Application\Webhook\ReceivePairrotWebhook;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API-02: Pairrot's webhook receiver. The `webhooks` firewall verified the HMAC signature (401 otherwise); here the
 * raw body goes to the inbox. Any verified request is answered `200` once the message is durable, a duplicate (S03)
 * included, because Pairrot retries everything else. A database outage surfaces as 5xx, which Pairrot retries too.
 */
#[AsController]
final readonly class PairrotWebhookController
{
    public function __construct(private CommandBus $commands) {}

    #[Route('/api/webhooks/pairrot', name: 'api_webhooks_pairrot', methods: ['POST'])]
    #[IsGranted('ROLE_PAIRROT')]
    #[OA\Post(
        summary: 'Receive a Pairrot webhook (signed with Pairrot-Webhook-Id / -Timestamp / -Signature)',
        tags: ['webhooks'],
        responses: [
            new OA\Response(response: 200, description: 'The event is stored (status "accepted") or was already known (status "duplicate")'),
            new OA\Response(response: 400, description: 'invalid_webhook: signed but not a Pairrot event'),
            new OA\Response(response: 401, description: 'invalid_signature: wrong key, wrong body or a timestamp outside +-5 minutes'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $status = $this->commands->dispatch(new ReceivePairrotWebhook($request->getContent()));

        return new JsonResponse(['status' => $status]);
    }
}
