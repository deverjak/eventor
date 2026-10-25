<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Query\OrganizerAdminQuery;
use Eventor\Identity\Application\UpdateProfile\UpdateOrganizerProfile;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\UserInterface\Http\Request\ProfileRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ORG-01 / ORG-05: name, contact, billing and identification data, the contact e-mail shown to participants and the
 * default look of the public pages.
 */
#[AsController]
final class OrganizerProfileController extends AbstractController
{
    public function __construct(
        private readonly OrganizerAdminQuery $query,
        private readonly CommandBus $commands,
    ) {}

    #[Route('/api/admin/org/{orgSlug}/profile', name: 'api_admin_organizer_profile', methods: ['GET'])]
    #[RequiresPermission(Permission::OrganizerView)]
    #[OA\Get(
        summary: 'Profile of the organizer (contact, billing data, branding)',
        tags: ['organizer'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'The profile'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 404, description: 'not_found: unknown organizer or not a member'),
        ],
    )]
    public function show(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json($this->query->profile($organizer->id));
    }

    #[Route('/api/admin/org/{orgSlug}/profile', name: 'api_admin_organizer_profile_update', methods: ['PUT'])]
    #[RequiresPermission(Permission::OrganizerSettingsManage)]
    #[OA\Put(
        summary: 'Replace the profile of the organizer (owner only). Omitted optional fields are cleared.',
        tags: ['organizer'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'contactEmail', type: 'string', format: 'email', nullable: true, description: 'Reply-To of messages to participants'),
                new OA\Property(property: 'contactPhone', type: 'string', nullable: true),
                new OA\Property(property: 'legalName', type: 'string', nullable: true),
                new OA\Property(property: 'companyId', type: 'string', nullable: true, description: 'Business identification number (ICO)'),
                new OA\Property(property: 'vatId', type: 'string', nullable: true),
                new OA\Property(property: 'billingStreet', type: 'string', nullable: true),
                new OA\Property(property: 'billingCity', type: 'string', nullable: true),
                new OA\Property(property: 'billingPostalCode', type: 'string', nullable: true),
                new OA\Property(property: 'billingCountry', type: 'string', nullable: true, example: 'CZ'),
                new OA\Property(property: 'logoUrl', type: 'string', nullable: true),
                new OA\Property(property: 'coverImageUrl', type: 'string', nullable: true),
                new OA\Property(property: 'brandColor', type: 'string', nullable: true, example: '#1a73e8'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'The saved profile'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden: only the owner may edit the profile'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    public function update(#[CurrentOrganizer] OrganizerView $organizer, #[MapRequestPayload] ProfileRequest $body): JsonResponse
    {
        $this->commands->dispatch(new UpdateOrganizerProfile(
            $organizer->id,
            $body->name,
            $body->contactEmail,
            $body->contactPhone,
            $body->legalName,
            $body->companyId,
            $body->vatId,
            $body->billingStreet,
            $body->billingCity,
            $body->billingPostalCode,
            $body->billingCountry,
            $body->logoUrl,
            $body->coverImageUrl,
            $body->brandColor,
        ));

        return $this->json($this->query->profile($organizer->id));
    }
}
