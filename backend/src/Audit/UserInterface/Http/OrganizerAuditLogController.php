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
 * AUD-04: the organizer-wide audit log, for owners (`audit.view.all`).
 */
#[AsController]
final class OrganizerAuditLogController extends AbstractController
{
    public function __construct(private readonly AuditLogQuery $log) {}

    #[Route('/api/admin/org/{orgSlug}/audit-log', name: 'api_admin_audit_log', methods: ['GET'])]
    #[RequiresPermission(Permission::AuditViewAll)]
    #[OA\Get(
        summary: 'The organizer-wide audit log, newest first (owners only)',
        tags: ['audit'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'entityType', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'actor', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['user', 'system', 'participant', 'device', 'pairrot'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 50, maximum: 200)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page: items, page, perPage, total'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: only audit.view.all (owner)'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
            new OA\Response(response: 422, description: 'validation_failed: unknown actor, page out of range'),
        ],
    )]
    public function __invoke(
        #[CurrentOrganizer]
        OrganizerView $organizer,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        OrganizerAuditQuery $query = new OrganizerAuditQuery(),
    ): JsonResponse {
        $this->denyAccessUnlessGranted(AuditScope::ATTRIBUTE, new AuditScope($organizer->id));

        $result = $this->log->page($organizer->id, $query->entityType, null, $query->actor, $query->page, $query->perPage);

        return $this->json($result->toArray());
    }
}
