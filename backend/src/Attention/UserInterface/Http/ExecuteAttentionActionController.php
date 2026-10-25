<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\Http;

use Eventor\Attention\Application\Action\AttentionActionRunner;
use Eventor\Attention\Application\Presenter\AttentionItemPresenter;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Domain\Id\AttentionItemId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[AsController]
final class ExecuteAttentionActionController extends AbstractController
{
    private const array NEEDS_RESOLVE_PERMISSION = ['resolve', 'dismiss'];

    public function __construct(
        private readonly AttentionActionRunner $runner,
        private readonly AttentionItemPresenter $presenter,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/attention/{itemId}/actions/{action}', name: 'api_admin_attention_action', requirements: ['itemId' => Requirement::UUID, 'action' => '[a-z_]+'], methods: ['POST'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Post(
        summary: 'Executes a command action of the item (acknowledge, resolve, dismiss) through the command bus',
        description: 'Resolving and dismissing additionally need payment.exception.resolve. Link actions are performed in the owning module.',
        tags: ['attention'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'action', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'resolve')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(properties: [new OA\Property(property: 'note', type: 'string', maxLength: 1000, nullable: true)])),
        responses: [
            new OA\Response(response: 200, description: 'The item after the action, incl. its remaining actions'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found (organizer) or attention_item_not_found'),
            new OA\Response(response: 422, description: 'attention_action_not_available or validation_failed'),
        ],
    )]
    public function __invoke(
        #[CurrentOrganizer]
        OrganizerView $organizer,
        string $itemId,
        string $action,
        #[MapRequestPayload]
        AttentionActionRequest $body = new AttentionActionRequest(),
    ): JsonResponse {
        if (\in_array($action, self::NEEDS_RESOLVE_PERMISSION, true)) {
            $this->denyAccessUnlessGranted(Permission::PaymentExceptionResolve->value, $organizer);
        }

        $note = null === $body->note || '' === trim($body->note) ? null : trim($body->note);
        $item = ($this->runner)($organizer->id, AttentionItemId::fromString($itemId), $action, $note);

        return $this->json($this->presenter->detail($item));
    }
}
