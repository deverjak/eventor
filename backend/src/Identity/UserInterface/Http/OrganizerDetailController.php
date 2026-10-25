<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Query\OrganizerDetailQuery;
use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The smallest end-to-end slice of the admin zone: firewall -> value resolver (tenant, 404) -> permission (403)
 * -> query service -> JSON. Every later admin endpoint follows this shape.
 */
#[AsController]
final class OrganizerDetailController extends AbstractController
{
    public function __construct(
        private readonly OrganizerDetailQuery $details,
        private readonly AccessPolicy $policy,
    ) {}

    #[Route('/api/admin/org/{orgSlug}', name: 'api_admin_organizer_detail', methods: ['GET'])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'Detail of the organizer the signed-in member belongs to',
        tags: ['organizer'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Organizer detail incl. your role'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks organizer.view'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, #[CurrentUser] UserInterface $user): JsonResponse
    {
        $role = $this->policy->roleOf($organizer->id, $user->getUserIdentifier()) ?? 'UNKNOWN';

        return $this->json($this->details->get($organizer->id, $role));
    }
}
