<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Payments\Application\Query\PairrotConnectionQuery;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Payments\Contract\PairrotOnboarding;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ORG-02 step 4 for the payments screens: whether the organizer is connected to Pairrot and whether the forwarded bank
 * notifications arrive. Ids and a masked IBAN only.
 */
#[AsController]
final class PairrotConnectionController extends AbstractController
{
    public function __construct(
        private readonly PairrotConnectionQuery $query,
        private readonly PairrotOnboarding $onboarding,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/payments/connection', name: 'api_admin_payments_connection', methods: ['GET'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Get(
        summary: 'Pairrot connection status of the organizer',
        tags: ['payments'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: '{connected, subtenantId, bankConnectionId, maskedIban, inboundAddress, receptionState (null when Pairrot cannot be reached), pairrotReachable}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        $connection = $this->query->forOrganizer($organizer->id);
        if (null === $connection || null === $connection->bankConnectionId) {
            return $this->json(['connected' => false, 'subtenantId' => $connection?->subtenantId, 'bankConnectionId' => null, 'maskedIban' => null, 'inboundAddress' => null, 'receptionState' => 'not_configured', 'pairrotReachable' => null]);
        }

        $reception = null;
        $reachable = true;
        try {
            $reception = $this->onboarding->receptionState($organizer->id, $connection->subtenantId, $connection->bankConnectionId)->value;
        } catch (PairrotUnavailable) {
            $reachable = false;
        }

        return $this->json([
            'connected' => true,
            'subtenantId' => $connection->subtenantId,
            'bankConnectionId' => $connection->bankConnectionId,
            'maskedIban' => $connection->maskedIban,
            'inboundAddress' => $connection->inboundAddress,
            'updatedAt' => $connection->updatedAt,
            'receptionState' => $reception,
            'pairrotReachable' => $reachable,
        ]);
    }
}
