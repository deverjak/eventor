<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Http\Capacity;

use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Registration\Application\Capacity\Query\CapacityBucketQuery;
use Eventor\Registration\Contract\Exception\CapacityBucketNotFound;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CAP-03, admin view: exact limit / held / confirmed / available of every capacity level of an event. Read straight
 * from the buckets, never from the availability cache. An event without buckets (unknown, a foreign tenant's, or
 * not yet published) is a 404.
 */
#[AsController]
final class CapacityOverviewController extends AbstractController
{
    public function __construct(private readonly CapacityBucketQuery $buckets) {}

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/capacity', name: 'api_admin_event_capacity', methods: ['GET'])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Get(
        summary: 'Capacity buckets of an event: limit, held, confirmed, available per level',
        tags: ['capacity'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Buckets ordered event, activity, variant, wave. `limit` and `available` are null for unlimited; `overbooked` after a limit was lowered below the occupancy (CAP-06).',
                content: new OA\JsonContent(
                    required: ['eventId', 'buckets'],
                    properties: [
                        new OA\Property(property: 'eventId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'buckets', type: 'array', items: new OA\Items(
                            required: ['bucketId', 'scope', 'scopeId', 'unit', 'limit', 'held', 'confirmed', 'available', 'overbooked'],
                            properties: [
                                new OA\Property(property: 'bucketId', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'scope', type: 'string', enum: ['event', 'activity', 'variant', 'wave']),
                                new OA\Property(property: 'scopeId', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'unit', type: 'string', enum: ['seat', 'team']),
                                new OA\Property(property: 'limit', type: 'integer', nullable: true),
                                new OA\Property(property: 'held', type: 'integer'),
                                new OA\Property(property: 'confirmed', type: 'integer'),
                                new OA\Property(property: 'available', type: 'integer', nullable: true),
                                new OA\Property(property: 'overbooked', type: 'boolean'),
                            ],
                            type: 'object',
                        )),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks event.configure'),
            new OA\Response(response: 404, description: 'not_found (unknown organizer or not a member) or capacity_bucket_not_found (event unknown or without buckets)'),
        ],
    )]
    public function __invoke(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        $event = EventId::isValid($eventId) ? EventId::fromString($eventId) : null;
        $details = null === $event ? [] : $this->buckets->detailsForEvent($organizer->id, $event);
        if (null === $event || [] === $details) {
            throw new CapacityBucketNotFound('The event has no capacity buckets.');
        }

        return $this->json(['eventId' => $event->toString(), 'buckets' => $details]);
    }
}
