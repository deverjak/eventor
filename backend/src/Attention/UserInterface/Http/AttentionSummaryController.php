<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\Http;

use Eventor\Attention\Contract\AttentionQueue;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class AttentionSummaryController extends AbstractController
{
    public function __construct(private readonly AttentionQueue $queue) {}

    #[Route('/api/admin/org/{orgSlug}/attention/summary', name: 'api_admin_attention_summary', methods: ['GET'])]
    #[RequiresPermission(Permission::PaymentExceptionAssign)]
    #[OA\Get(
        summary: 'Counts of unresolved (open + acknowledged) items per type, for the dashboard badge',
        tags: ['attention'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'unresolved total and byType'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
            new OA\Response(response: 422, description: 'validation_failed: malformed event id'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $eventId = $request->query->get('eventId');
        if (null !== $eventId && (!\is_string($eventId) || !EventId::isValid($eventId))) {
            throw new UnprocessableEntityHttpException('eventId must be a UUID.');
        }

        $summary = $this->queue->summary($organizer->id, null === $eventId ? null : EventId::fromString($eventId));

        return $this->json(['unresolved' => $summary->open, 'byType' => (object) $summary->byType]);
    }
}
