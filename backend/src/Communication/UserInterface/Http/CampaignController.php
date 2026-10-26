<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http;

use Eventor\Communication\Application\Campaign\CampaignDrafting;
use Eventor\Communication\Application\Campaign\CancelCampaign;
use Eventor\Communication\Application\Campaign\CreateCampaign;
use Eventor\Communication\Application\Campaign\ScheduleCampaign;
use Eventor\Communication\Application\Campaign\StartCampaign;
use Eventor\Communication\Application\Campaign\UpdateCampaign;
use Eventor\Communication\Application\Query\CampaignQuery;
use Eventor\Communication\Application\Query\TemplatePreviewQuery;
use Eventor\Communication\UserInterface\Http\Request\CampaignRequest;
use Eventor\Communication\UserInterface\Http\Request\CampaignUpdateRequest;
use Eventor\Communication\UserInterface\Http\Request\ListQuery;
use Eventor\Communication\UserInterface\Http\Request\ScheduleCampaignRequest;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\EventId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Own messages of the organizer (COM-03, COM-04): draft, preview with the recipient count, send now or schedule, cancel.
 * Marketing campaigns are always narrowed to people with a granted consent (PRV-07); that cannot be switched off here.
 */
#[AsController]
#[RequiresPermission(Permission::MessageSend)]
final class CampaignController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly CampaignQuery $campaigns,
        private readonly CampaignDrafting $drafting,
        private readonly TemplatePreviewQuery $previews,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/campaigns', name: 'api_admin_campaign_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Campaigns, newest first, with the number of deliveries per state',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{items, page, perPage, total}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ListQuery $query = new ListQuery()): JsonResponse
    {
        $page = $this->campaigns->page($organizer->id, null === $query->eventId ? null : EventId::fromString($query->eventId), $query->campaignState, $query->page, $query->perPage);

        return $this->json(['items' => $page->items, 'page' => $query->page, 'perPage' => $query->perPage, 'total' => $page->total]);
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns', name: 'api_admin_campaign_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Creates a campaign as a draft; without subject and body the wording of templateKey is used',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 201, description: '{campaignId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or event_not_found'),
            new OA\Response(response: 422, description: 'validation_failed, invalid_campaign or template_invalid'),
        ],
    )]
    public function create(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] CampaignRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new CreateCampaign(
            $body->eventId,
            $body->name,
            $body->purpose,
            $body->locale,
            $body->templateKey,
            $body->subject,
            $body->body,
            $body->audience->spec(),
        )), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/preview', name: 'api_admin_campaign_preview', methods: ['POST'])]
    #[OA\Post(
        summary: 'Without saving: the rendered message and how many deliveries the audience gives right now',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{subject, body, recipientCount}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or event_not_found'),
            new OA\Response(response: 422, description: 'validation_failed, invalid_campaign or template_invalid'),
        ],
    )]
    public function preview(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] CampaignRequest $body): JsonResponse
    {
        $audience = $body->audience->spec();
        $event = EventId::fromString($body->eventId);
        $this->drafting->assertTarget($organizer->id, $event, $audience);
        $wording = $this->drafting->wording($body->templateKey, $body->locale, $body->subject, $body->body);
        $preview = $this->previews->preview($organizer->id, null, $body->locale, $wording['subject'], $wording['body']);

        return $this->json($this->campaigns->preview($organizer->id, $event, $audience, $body->purpose, $preview->subject, $preview->body));
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}', name: 'api_admin_campaign_get', methods: ['GET'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'A campaign with its wording, targeting and delivery counts',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The campaign'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
        ],
    )]
    public function get(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId): JsonResponse
    {
        return $this->json($this->campaigns->detail($organizer->id, CampaignId::fromString($campaignId)));
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}', name: 'api_admin_campaign_update', methods: ['PUT'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Put(
        summary: 'Edits a draft campaign',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The campaign after the change'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
            new OA\Response(response: 409, description: 'campaign_state_invalid: only a draft can be edited'),
            new OA\Response(response: 422, description: 'validation_failed, invalid_campaign or template_invalid'),
        ],
    )]
    public function update(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId, #[MapRequestPayload] CampaignUpdateRequest $body): JsonResponse
    {
        $this->commands->dispatch(new UpdateCampaign($campaignId, $body->name, $body->purpose, $body->locale, $body->templateKey, $body->subject, $body->body, $body->audience->spec()));

        return $this->json($this->campaigns->detail($organizer->id, CampaignId::fromString($campaignId)));
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}/send', name: 'api_admin_campaign_send', methods: ['POST'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Sends the campaign now: one delivery per orderer and activity, each re-checked just before sending',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{recipients}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
            new OA\Response(response: 409, description: 'campaign_state_invalid'),
        ],
    )]
    public function send(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId): JsonResponse
    {
        return $this->json($this->commands->dispatch(new StartCampaign($campaignId)));
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}/schedule', name: 'api_admin_campaign_schedule', methods: ['POST'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Schedules the campaign for a future moment',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The campaign'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
            new OA\Response(response: 409, description: 'campaign_state_invalid'),
            new OA\Response(response: 422, description: 'validation_failed or invalid_campaign (not in the future)'),
        ],
    )]
    public function schedule(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId, #[MapRequestPayload] ScheduleCampaignRequest $body): JsonResponse
    {
        $this->commands->dispatch(new ScheduleCampaign($campaignId, $body->at));

        return $this->json($this->campaigns->detail($organizer->id, CampaignId::fromString($campaignId)));
    }

    #[Route('/api/admin/org/{orgSlug}/campaigns/{campaignId}/cancel', name: 'api_admin_campaign_cancel', methods: ['POST'], requirements: ['campaignId' => Requirement::UUID])]
    #[OA\Post(
        summary: 'Cancels a draft or scheduled campaign',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The campaign'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or campaign_not_found'),
            new OA\Response(response: 409, description: 'campaign_state_invalid'),
        ],
    )]
    public function cancel(#[CurrentOrganizer] OrganizerView $organizer, string $campaignId): JsonResponse
    {
        $this->commands->dispatch(new CancelCampaign($campaignId));

        return $this->json($this->campaigns->detail($organizer->id, CampaignId::fromString($campaignId)));
    }
}
