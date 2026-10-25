<?php

declare(strict_types=1);

namespace Eventor\Catalog\UserInterface\Http;

use Eventor\Catalog\Application\Program\AddProgramItem;
use Eventor\Catalog\Application\Program\CreateNewsPost;
use Eventor\Catalog\Application\Program\NewsPostInput;
use Eventor\Catalog\Application\Program\ProgramItemInput;
use Eventor\Catalog\Application\Program\PublishNewsPost;
use Eventor\Catalog\Application\Program\RemoveProgramItem;
use Eventor\Catalog\Application\Program\UpdateNewsPost;
use Eventor\Catalog\Application\Program\UpdateProgramItem;
use Eventor\Catalog\Application\Query\AdminProgramQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\NewsPostId;
use Eventor\Shared\Domain\Id\ProgramItemId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Program (PRG-01..03) and news (PRG-04) of an event. Reads are open to every member, writes need `program.manage`.
 */
#[AsController]
final class ProgramController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AdminProgramQuery $program,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/program', name: 'api_admin_program_list', methods: ['GET'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'The program of the event, by start time (all visibilities)',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'items'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json(['items' => $this->program->program($organizer->id, EventId::fromString($eventId))]);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/program', name: 'api_admin_program_add', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ProgramManage)]
    #[OA\Post(
        summary: 'Add a program item, optionally linked to an activity',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'programItemId and the program'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks program.manage'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / activity_not_found'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function add(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] ProgramItemInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        /** @var array{programItemId: string} $result */
        $result = $this->commands->dispatch(new AddProgramItem($id, $input));

        return $this->json(['programItemId' => $result['programItemId'], 'items' => $this->program->program($organizer->id, $id)], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/program/{itemId}', name: 'api_admin_program_update', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID, 'itemId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ProgramManage)]
    #[OA\Put(
        summary: 'Replace a program item; `important` offers the notification of the affected participants',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The program'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks program.manage'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / program_item_not_found'),
            new OA\Response(response: 422, description: 'validation_failed / invalid_catalog_data'),
        ],
    )]
    public function update(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $itemId, #[MapRequestPayload] ProgramItemInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new UpdateProgramItem($id, ProgramItemId::fromString($itemId), $input));

        return $this->json(['items' => $this->program->program($organizer->id, $id)]);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/program/{itemId}', name: 'api_admin_program_remove', methods: ['DELETE'], requirements: ['eventId' => Requirement::UUID, 'itemId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ProgramManage)]
    #[OA\Delete(
        summary: 'Remove a program item',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The program'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks program.manage'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / program_item_not_found'),
        ],
    )]
    public function remove(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $itemId): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new RemoveProgramItem($id, ProgramItemId::fromString($itemId)));

        return $this->json(['items' => $this->program->program($organizer->id, $id)]);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/news', name: 'api_admin_news_list', methods: ['GET'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'News posts of the event, drafts included',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'items'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
        ],
    )]
    public function newsList(#[CurrentOrganizer] OrganizerView $organizer, string $eventId): JsonResponse
    {
        return $this->json(['items' => $this->program->news($organizer->id, EventId::fromString($eventId))]);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/news', name: 'api_admin_news_create', methods: ['POST'], requirements: ['eventId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ProgramManage)]
    #[OA\Post(
        summary: 'Create a news post (`publish` publishes at once, `broadcast` also sends it by e-mail)',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'newsPostId, published and the posts'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks program.manage'),
            new OA\Response(response: 404, description: 'not_found / event_not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function newsCreate(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, #[MapRequestPayload] NewsPostInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        /** @var array{newsPostId: string, published: bool} $result */
        $result = $this->commands->dispatch(new CreateNewsPost($id, $input));

        return $this->json($result + ['items' => $this->program->news($organizer->id, $id)], Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/news/{postId}', name: 'api_admin_news_update', methods: ['PUT'], requirements: ['eventId' => Requirement::UUID, 'postId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ProgramManage)]
    #[OA\Put(
        summary: 'Edit a draft news post',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The posts'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks program.manage'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / news_post_not_found'),
            new OA\Response(response: 409, description: 'news_post_already_published'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function newsUpdate(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $postId, #[MapRequestPayload] NewsPostInput $input): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new UpdateNewsPost($id, NewsPostId::fromString($postId), $input));

        return $this->json(['items' => $this->program->news($organizer->id, $id)]);
    }

    #[Route('/api/admin/org/{orgSlug}/events/{eventId}/news/{postId}/publish', name: 'api_admin_news_publish', methods: ['POST'], requirements: ['eventId' => Requirement::UUID, 'postId' => Requirement::UUID])]
    #[RequiresPermission(Permission::ProgramManage)]
    #[OA\Post(
        summary: 'Publish a news post',
        tags: ['program'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The posts'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: the role lacks program.manage'),
            new OA\Response(response: 404, description: 'not_found / event_not_found / news_post_not_found'),
            new OA\Response(response: 409, description: 'news_post_already_published'),
        ],
    )]
    public function newsPublish(#[CurrentOrganizer] OrganizerView $organizer, string $eventId, string $postId): JsonResponse
    {
        $id = EventId::fromString($eventId);
        $this->commands->dispatch(new PublishNewsPost($id, NewsPostId::fromString($postId)));

        return $this->json(['items' => $this->program->news($organizer->id, $id)]);
    }
}
