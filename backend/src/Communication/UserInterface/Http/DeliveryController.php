<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http;

use Eventor\Communication\Application\Delivery\RetryFailedDeliveries;
use Eventor\Communication\Application\Query\CampaignQuery;
use Eventor\Communication\Application\Query\DeliveryQuery;
use Eventor\Communication\UserInterface\Http\Request\ListQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\OrderId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Delivery history per recipient and the retry of the failed ones (COM-06). The retry keeps the idempotency key, so a
 * message that was in fact sent is never sent twice. Attention's "retry failed deliveries" action points here.
 */
#[AsController]
#[RequiresPermission(Permission::MessageSend)]
final class DeliveryController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly DeliveryQuery $deliveries,
        private readonly CampaignQuery $campaigns,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/deliveries', name: 'api_admin_delivery_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Delivery history (state, time, error, masked recipient), filterable by campaign, state and order',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{items, page, perPage, total}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListQuery $query = new ListQuery()): JsonResponse
    {
        $page = $this->deliveries->page(
            $organizer->id,
            null === $query->campaignId ? null : CampaignId::fromString($query->campaignId),
            $query->state,
            null === $query->orderId ? null : OrderId::fromString($query->orderId),
            $query->page,
            $query->perPage,
        );

        return $this->json(['items' => $page->items, 'page' => $query->page, 'perPage' => $query->perPage, 'total' => $page->total]);
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}/deliveries', name: 'api_admin_campaign_deliveries', methods: ['GET'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'Delivery history of one campaign',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{items, page, perPage, total}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function ofCampaign(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListQuery $query = new ListQuery()): JsonResponse
    {
        $id = CampaignId::fromString($campaignId);
        $this->campaigns->detail($organizer->id, $id);
        $page = $this->deliveries->page($organizer->id, $id, $query->state, null, $query->page, $query->perPage);

        return $this->json(['items' => $page->items, 'page' => $query->page, 'perPage' => $query->perPage, 'total' => $page->total]);
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}/deliveries/retry', name: 'api_admin_campaign_retry', methods: ['POST'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Retries only the failed deliveries of a campaign (no duplicates: same idempotency keys)',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{retried}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
        ],
    )]
    public function retryCampaign(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new RetryFailedDeliveries(campaignId: $campaignId)));
    }

    #[Route('/api/admin/org/{orgSlug}/deliveries/{deliveryId}', name: 'api_admin_delivery_get', methods: ['GET'], requirements: ['deliveryId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'One delivery',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The delivery'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or delivery_not_found'),
        ],
    )]
    public function get(#[CurrentOrganizer] OrganizerView $organizer, string $deliveryId): JsonResponse
    {
        return $this->json($this->deliveries->get($organizer->id, $deliveryId));
    }

    #[Route('/api/admin/org/{orgSlug}/deliveries/{deliveryId}/retry', name: 'api_admin_delivery_retry', methods: ['POST'], requirements: ['deliveryId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Retries one failed delivery',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{retried}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or delivery_not_found'),
            new OA\Response(response: 409, description: 'delivery_not_retryable: only a failed delivery can be retried'),
        ],
    )]
    public function retry(#[CurrentOrganizer] OrganizerView $organizer, string $deliveryId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new RetryFailedDeliveries(deliveryId: $deliveryId)));
    }
}
