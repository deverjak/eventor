<?php

declare(strict_types=1);

namespace Eventor\Audit\UserInterface\Http;

use Eventor\Audit\Application\Query\AuditLogQuery;
use Eventor\Audit\UserInterface\Security\AuditScope;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * AUD-04: the history of one entity. Any member may call it; what the role may see is decided by the `AuditVoter`
 * (owner: all, finance: money entities, event manager: the rest).
 */
#[AsController]
final class EntityAuditController extends AbstractController
{
    public function __construct(private readonly AuditLogQuery $log) {}

    #[Route('/api/admin/org/{orgSlug}/audit', name: 'api_admin_audit_entity', methods: ['GET'])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'Audit history of one entity, newest first, optionally only one kind of actor (AUD-03, AUD-04)',
        tags: ['audit'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'entityType', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'event')),
            new OA\Parameter(name: 'entityId', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'actor', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['user', 'system', 'participant', 'device', 'pairrot'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 200)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page: items, page, perPage, total'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role may not see audit entries of this entity type'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
            new OA\Response(response: 422, description: 'validation_failed: entityType or entityId missing, unknown actor'),
        ],
    )]
    public function __invoke(
        #[CurrentOrganizer]
        OrganizerView $organizer,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        EntityAuditQuery $query,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(AuditScope::ATTRIBUTE, new AuditScope($organizer->id, $query->entityType));

        $result = $this->log->page($organizer->id, $query->entityType, $query->entityId, $query->actor, $query->page, $query->perPage);

        return $this->json($result->toArray());
    }
}
