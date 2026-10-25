<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Eventor\Catalog\Application\Query\PublicAvailabilityQuery;
use Eventor\Catalog\Application\Query\PublicEventPageQuery;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerView;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Catalog side of the public event page (PUB-01..03). No session: the organizer comes from the URL slug and an
 * unknown organizer or event is a plain 404. Drafts do not exist here.
 */
#[AsController]
final class PublicEventController extends AbstractController
{
    public function __construct(
        private readonly OrganizerLookup $organizers,
        private readonly PublicEventPageQuery $events,
        private readonly PublicAvailabilityQuery $availability,
    ) {}

    #[Route('/api/public/orgs/{orgSlug}/events', name: 'api_public_event_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Public events of the organizer (published and completed, visibility public)',
        tags: ['public'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'items'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer'),
        ],
    )]
    public function list(string $orgSlug): JsonResponse
    {
        return $this->json($this->events->list($this->organizer($orgSlug)->id));
    }

    #[Route('/api/public/orgs/{orgSlug}/events/{eventSlug}', name: 'api_public_event_detail', methods: ['GET'])]
    #[OA\Get(
        summary: 'A published event: description, activities, variants with prices and availability, program, news and the form schema',
        tags: ['public'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The public event page'),
            new OA\Response(response: 404, description: 'not_found / event_not_found: unknown organizer, unknown event or a draft'),
        ],
    )]
    public function detail(string $orgSlug, string $eventSlug): JsonResponse
    {
        return $this->json($this->events->detail($this->organizer($orgSlug)->id, $eventSlug));
    }

    #[Route('/api/public/orgs/{orgSlug}/events/{eventSlug}/form', name: 'api_public_event_form', methods: ['GET'])]
    #[OA\Get(
        summary: 'The current registration form of a published event (fields, JSON Schema fragments, conditions)',
        tags: ['public'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'formVersionId, number, schemaSha256 and fields'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
        ],
    )]
    public function form(string $orgSlug, string $eventSlug): JsonResponse
    {
        return $this->json($this->events->formOf($this->organizer($orgSlug)->id, $eventSlug));
    }

    #[Route('/api/public/orgs/{orgSlug}/events/{eventSlug}/availability', name: 'api_public_event_availability', methods: ['GET'])]
    #[OA\Get(
        summary: 'Free places of a published event by event, activity and variant (available / low / sold_out / unlimited)',
        tags: ['public'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'eventId, shown, event, activities and variants (keyed by id)'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
        ],
    )]
    public function availability(string $orgSlug, string $eventSlug): JsonResponse
    {
        return $this->json($this->availability->forSlug($this->organizer($orgSlug)->id, $eventSlug));
    }

    private function organizer(string $slug): OrganizerView
    {
        return $this->organizers->findBySlug($slug) ?? throw new NotFoundHttpException('Organizer not found.');
    }
}
