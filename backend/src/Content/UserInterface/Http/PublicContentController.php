<?php

declare(strict_types=1);

namespace Eventor\Content\UserInterface\Http;

use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Content\Application\Port\PhotoStorage;
use Eventor\Content\Application\Query\PublicContentReaderQuery;
use Eventor\Content\Contract\CollectionVisibility;
use Eventor\Content\Contract\Exception\CollectionNotFound;
use Eventor\Content\Contract\Exception\PhotoNotFound;
use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Shared\Domain\Id\CollectionId;
use Eventor\Shared\Domain\Id\PhotoId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Galleries and materials for visitors: the public event page (PUB-06), link-only collections (POST-03), the public
 * archive of a finished event (POST-07) and the photo bytes. Only published content, never personal data.
 */
final class PublicContentController extends AbstractController
{
    private const string EVENT = '/api/public/orgs/{orgSlug}/events/{eventSlug}';

    public function __construct(
        private readonly OrganizerLookup $organizers,
        private readonly EventCatalog $events,
        private readonly PublicContentReaderQuery $content,
        private readonly PhotoStorage $storage,
    ) {}

    #[Route(self::EVENT.'/content', name: 'api_public_content', methods: ['GET'])]
    #[OA\Get(summary: 'Published public collections and materials of a public event page (PUB-06)', tags: ['public'], responses: [
        new OA\Response(response: 200, description: '{collections: [...], materials: [...]}'),
        new OA\Response(response: 404, description: 'not_found or event_not_found'),
    ])]
    public function page(string $orgSlug, string $eventSlug): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);

        return $this->json($this->content->page($organizer->id, $this->events->resolvePublishedSlug($organizer->id, $eventSlug)));
    }

    #[Route(self::EVENT.'/archive', name: 'api_public_content_archive', methods: ['GET'])]
    #[OA\Get(summary: 'The public archive of a completed or archived event: only designated content (POST-07)', tags: ['public'], responses: [
        new OA\Response(response: 200, description: '{collections: [...], materials: [...]}'),
        new OA\Response(response: 404, description: 'not_found or event_not_found (unknown or not finished)'),
    ])]
    public function archive(string $orgSlug, string $eventSlug): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);

        return $this->json($this->content->archive($organizer->id, $this->events->resolveArchivedSlug($organizer->id, $eventSlug)));
    }

    #[Route(self::EVENT.'/collections/{collectionId}/photos', name: 'api_public_content_photos', methods: ['GET'], requirements: ['collectionId' => Requirement::UUID])]
    #[OA\Get(summary: 'Photos of a published public collection', tags: ['public'], parameters: [
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 100)),
        new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer')),
    ], responses: [
        new OA\Response(response: 200, description: '{items: [...]}'),
        new OA\Response(response: 404, description: 'collection_not_found or event_not_found'),
    ])]
    public function photos(string $orgSlug, string $eventSlug, string $collectionId, Request $request): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);
        $this->events->resolvePublishedSlug($organizer->id, $eventSlug);

        return $this->json(['items' => $this->content->photos(
            $organizer->id,
            CollectionId::fromString($collectionId),
            CollectionVisibility::Public,
            $request->query->getInt('limit', 60),
            $request->query->getInt('offset'),
        )]);
    }

    #[Route(self::EVENT.'/collection-links/{token}', name: 'api_public_content_link', methods: ['GET'], requirements: ['token' => '[a-f0-9]{48}'])]
    #[OA\Get(summary: 'A link-only collection opened through its unguessable link (POST-03)', tags: ['public'], responses: [
        new OA\Response(response: 200, description: '{collectionId, items: [...]}'),
        new OA\Response(response: 404, description: 'collection_not_found'),
    ])]
    public function link(string $orgSlug, string $eventSlug, string $token): JsonResponse
    {
        $organizer = $this->organizer($orgSlug);
        $event = $this->events->resolvePublishedSlug($organizer->id, $eventSlug);
        $collection = $this->content->linkOnly($organizer->id, $event, $token) ?? throw new CollectionNotFound('This link does not lead to a collection.');

        return $this->json(['collectionId' => $collection->toString(), 'items' => $this->content->photos($organizer->id, $collection, CollectionVisibility::LinkOnly)]);
    }

    #[Route('/api/public/content/photos/{photoId}/{variant}', name: 'api_public_content_photo_file', methods: ['GET'], requirements: ['photoId' => Requirement::UUID, 'variant' => 'original|thumbnail'], defaults: ['variant' => 'original'])]
    #[OA\Get(summary: 'The image of a published photo (a link-only one needs ?token=)', tags: ['public'], parameters: [
        new OA\Parameter(name: 'token', in: 'query', schema: new OA\Schema(type: 'string')),
    ], responses: [
        new OA\Response(response: 200, description: 'image/jpeg, image/png or image/webp'),
        new OA\Response(response: 404, description: 'photo_not_found'),
    ])]
    public function image(string $photoId, string $variant, Request $request): Response
    {
        $id = PhotoId::fromString($photoId);
        $token = $request->query->getString('token');
        $file = $this->content->photoFile($id, '' === $token ? null : $token) ?? throw PhotoNotFound::forId($id);
        $thumbnail = 'thumbnail' === $variant && null !== $file->thumbnailKey;

        return new Response(
            $this->storage->read($thumbnail ? (string) $file->thumbnailKey : $file->storageKey),
            Response::HTTP_OK,
            [
                'Content-Type' => $thumbnail ? 'image/jpeg' : $file->contentType,
                // A link-only photo must not land in shared caches: whoever has the link may see it, nobody else.
                'Cache-Control' => ('' === $token ? 'public' : 'private').', max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function organizer(string $slug): OrganizerView
    {
        return $this->organizers->findBySlug($slug) ?? throw OrganizerNotFound::forSlug($slug);
    }
}
