<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\Http;

use Eventor\Attention\Application\Presenter\AttentionItemPresenter;
use Eventor\Attention\Application\Query\AttentionItemQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ListAttentionController extends AbstractController
{
    public function __construct(
        private readonly AttentionItemQuery $items,
        private readonly AttentionItemPresenter $presenter,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/attention', name: 'api_admin_attention_list', methods: ['GET'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Get(
        summary: 'The "needs attention" queue (ADM-03), newest first, filterable by type, state and event',
        tags: ['attention'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'late_payment')),
            new OA\Parameter(name: 'state', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['open', 'acknowledged', 'resolved', 'dismissed'])),
            new OA\Parameter(name: 'eventId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page: items, page, perPage, total'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks payment.exception.assign'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
            new OA\Response(response: 422, description: 'validation_failed: unknown type or state, bad event id, page out of range'),
        ],
    )]
    public function __invoke(
        #[CurrentOrganizer]
        OrganizerView $organizer,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        AttentionListQuery $query = new AttentionListQuery(),
    ): JsonResponse {
        $result = $this->items->page(
            $organizer->id,
            $query->type,
            $query->state,
            null === $query->eventId ? null : EventId::fromString($query->eventId),
            $query->page,
            $query->perPage,
        );

        return $this->json([
            'items' => array_map($this->presenter->summary(...), $result->items),
            'page' => $query->page,
            'perPage' => $query->perPage,
            'total' => $result->total,
        ]);
    }
}
