<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\Http;

use Eventor\Attention\Application\Presenter\AttentionItemPresenter;
use Eventor\Attention\Application\Query\AttentionItemQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Domain\Id\AttentionItemId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final class GetAttentionItemController extends AbstractController
{
    public function __construct(
        private readonly AttentionItemQuery $items,
        private readonly AttentionItemPresenter $presenter,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/attention/{itemId}', name: 'api_admin_attention_detail', requirements: ['itemId' => Requirement::UUID], methods: ['GET'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Get(
        summary: 'One queue item with the actions it offers',
        tags: ['attention'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The item incl. actions [{key, kind: command|link, noteAllowed, target}]'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found (organizer) or attention_item_not_found'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $itemId): JsonResponse
    {
        $id = AttentionItemId::fromString($itemId);
        $item = $this->items->get($organizer->id, $id);

        return $this->json($this->presenter->detail($item));
    }
}
