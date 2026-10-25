<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Legal\Application\Query\ConfirmationListQuery;
use Eventor\Legal\Contract\ConsentDecision;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Read-only view of the append-only confirmation ledger (LEG-07). Confirmations are written by Registration and
 * CheckIn through `ConfirmationRecorder`, never through this API.
 */
#[AsController]
#[RequiresPermission(Permission::EventLegalManage)]
final class ConfirmationController extends AbstractController
{
    public function __construct(private readonly ConfirmationListQuery $confirmations) {}

    #[Route('/api/admin/org/{orgSlug}/legal/confirmations', name: 'api_admin_legal_confirmation_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Confirmation records, newest first; filter by requirement, participation, order or decision',
        tags: ['legal'],
        parameters: [
            new OA\Parameter(name: 'requirementId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'participationId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'orderId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'decision', in: 'query', schema: new OA\Schema(type: 'string', enum: ['requested', 'granted', 'declined', 'withdrawn'])),
            new OA\Parameter(name: 'current', in: 'query', description: 'Only the current decision of every chain', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 200)),
            new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 0)),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [...], total}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $decision = $request->query->getString('decision');
        if ('' !== $decision && null === ConsentDecision::tryFrom($decision)) {
            throw new UnprocessableEntityHttpException('Unknown decision filter.');
        }

        return $this->json($this->confirmations->list(
            $organizer->id,
            $this->uuid($request, 'requirementId'),
            $this->uuid($request, 'participationId'),
            $this->uuid($request, 'orderId'),
            '' === $decision ? null : $decision,
            $request->query->getBoolean('current'),
            max(1, min(200, $request->query->getInt('limit', 50))),
            max(0, $request->query->getInt('offset')),
        ));
    }

    private function uuid(Request $request, string $name): ?string
    {
        $value = $request->query->getString($name);
        if ('' === $value) {
            return null;
        }
        if (!Uuid::isValid($value)) {
            throw new UnprocessableEntityHttpException(\sprintf('"%s" must be a UUID.', $name));
        }

        return strtolower($value);
    }
}
