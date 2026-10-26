<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http;

use Eventor\Communication\Application\Query\SuppressionQuery;
use Eventor\Communication\Application\Suppression\AddSuppression;
use Eventor\Communication\Application\Suppression\LiftSuppression;
use Eventor\Communication\UserInterface\Http\Request\SuppressionRequest;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * The suppression list (PRV-07, COM-09): addresses that get no message of a scope. Only hashes are stored.
 */
#[AsController]
#[RequiresPermission(Permission::MessageSend)]
final class SuppressionController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SuppressionQuery $suppressions,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/suppressions', name: 'api_admin_suppression_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'The suppression list (ids and scopes, never addresses)',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{items}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json(['items' => $this->suppressions->list($organizer->id)]);
    }

    #[Route('/api/admin/org/{orgSlug}/suppressions', name: 'api_admin_suppression_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Puts an address on the suppression list (idempotent)',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 201, description: '{suppressionId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function add(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] SuppressionRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new AddSuppression($body->email, $body->scope)), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/suppressions/{suppressionId}', name: 'api_admin_suppression_lift', methods: ['DELETE'], requirements: ['suppressionId' => Requirement::UUID])]
    #[OA\Delete(
        summary: 'Takes an address off the suppression list',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 204, description: 'Removed'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or suppression_not_found'),
        ],
    )]
    public function lift(#[CurrentOrganizer] OrganizerView $organizer, string $suppressionId): Response
    {
        $this->commands->dispatch(new LiftSuppression($suppressionId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
