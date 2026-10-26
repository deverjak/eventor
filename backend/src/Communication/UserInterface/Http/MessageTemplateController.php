<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http;

use Eventor\Communication\Application\Query\MessageTemplateQuery;
use Eventor\Communication\Application\Query\TemplatePreviewQuery;
use Eventor\Communication\Application\Template\ChangeMessageTemplate;
use Eventor\Communication\Application\Template\CreateMessageTemplate;
use Eventor\Communication\Application\Template\RemoveMessageTemplate;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\UserInterface\Http\Request\TemplatePreviewRequest;
use Eventor\Communication\UserInterface\Http\Request\TemplateRequest;
use Eventor\Communication\UserInterface\Http\Request\TemplateTextRequest;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Message templates of an organizer (COM-08): its own wording per message and language over the built-in texts, the
 * list of variables, and a preview with sample data.
 */
#[AsController]
#[RequiresPermission(Permission::MessageSend)]
final class MessageTemplateController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly MessageTemplateQuery $templates,
        private readonly TemplatePreviewQuery $previews,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/message-templates', name: 'api_admin_template_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Every message template with the text that applies (own or built-in), optionally of one key and language',
        tags: ['communication'],
        parameters: [
            new OA\Parameter(name: 'key', in: 'query', schema: new OA\Schema(type: 'string', example: 'payment_reminder')),
            new OA\Parameter(name: 'locale', in: 'query', schema: new OA\Schema(type: 'string', enum: ['cs', 'en'])),
        ],
        responses: [
            new OA\Response(response: 200, description: '{items: [{id|null, key, locale, subject, body, source: custom|built_in}]}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer, Request $request): JsonResponse
    {
        $key = TemplateKey::tryFrom($request->query->getString('key'));
        $locale = MessageLocale::tryFrom($request->query->getString('locale'));

        return $this->json(['items' => $this->templates->list($organizer->id, $key, $locale)]);
    }

    #[Route('/api/admin/org/{orgSlug}/message-templates/variables', name: 'api_admin_template_variables', methods: ['GET'])]
    #[OA\Get(
        summary: 'The variables a template may use',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{variables: {name: meaning}}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function variables(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json(['variables' => $this->templates->variables()]);
    }

    #[Route('/api/admin/org/{orgSlug}/message-templates/preview', name: 'api_admin_template_preview', methods: ['POST'])]
    #[OA\Post(
        summary: 'Renders a text (or the template of a key) with sample data in the sandbox',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: '{subject, body}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed or template_invalid (unknown variable, forbidden tag)'),
        ],
    )]
    public function preview(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] TemplatePreviewRequest $body): JsonResponse
    {
        return $this->json($this->previews->preview($organizer->id, $body->key, $body->locale, $body->subject, $body->body));
    }

    #[Route('/api/admin/org/{orgSlug}/message-templates', name: 'api_admin_template_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Creates the organizer\'s own wording of a message in one language',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 201, description: '{templateId}'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 409, description: 'template_exists'),
            new OA\Response(response: 422, description: 'validation_failed or template_invalid'),
        ],
    )]
    public function create(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] TemplateRequest $body): JsonResponse
    {
        return $this->json($this->commands->dispatch(new CreateMessageTemplate($body->key, $body->locale, $body->subject, $body->body)), Response::HTTP_CREATED);
    }

    #[Route('/api/admin/org/{orgSlug}/message-templates/{templateId}', name: 'api_admin_template_get', methods: ['GET'], requirements: ['templateId' => Requirement::UUID])]
    #[OA\Get(
        summary: 'One own template',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The template'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or message_template_not_found'),
        ],
    )]
    public function get(#[CurrentOrganizer] OrganizerView $organizer, string $templateId): JsonResponse
    {
        return $this->json($this->templates->get($organizer->id, $templateId));
    }

    #[Route('/api/admin/org/{orgSlug}/message-templates/{templateId}', name: 'api_admin_template_update', methods: ['PUT'], requirements: ['templateId' => Requirement::UUID])]
    #[OA\Put(
        summary: 'Changes the subject and body of an own template',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 200, description: 'The template after the change'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or message_template_not_found'),
            new OA\Response(response: 422, description: 'validation_failed or template_invalid'),
        ],
    )]
    public function update(#[CurrentOrganizer] OrganizerView $organizer, string $templateId, #[MapRequestPayload] TemplateTextRequest $body): JsonResponse
    {
        $this->commands->dispatch(new ChangeMessageTemplate($templateId, $body->subject, $body->body));

        return $this->json($this->templates->get($organizer->id, $templateId));
    }

    #[Route('/api/admin/org/{orgSlug}/message-templates/{templateId}', name: 'api_admin_template_delete', methods: ['DELETE'], requirements: ['templateId' => Requirement::UUID])]
    #[OA\Delete(
        summary: 'Removes the own wording; the built-in text applies again',
        tags: ['communication'],
        responses: [
            new OA\Response(response: 204, description: 'Removed'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found or message_template_not_found'),
        ],
    )]
    public function delete(#[CurrentOrganizer] OrganizerView $organizer, string $templateId): Response
    {
        $this->commands->dispatch(new RemoveMessageTemplate($templateId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
