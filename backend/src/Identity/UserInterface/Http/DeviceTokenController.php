<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\DeviceToken\IssueDeviceToken;
use Eventor\Identity\Application\DeviceToken\RevokeDeviceToken;
use Eventor\Identity\Application\Query\DeviceTokenQuery;
use Eventor\Identity\Contract\Attribute\CurrentOrganizer;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\UserInterface\Http\Request\IssueDeviceTokenRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Security\IdentifiableUser;
use Eventor\Shared\Domain\Id\CheckInListId;
use Eventor\Shared\Domain\Id\UserAccountId;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Credentials of check-in devices (ADR 006, CHK-01): issue (the token is shown once), list, revoke.
 */
#[AsController]
final class DeviceTokenController extends AbstractController
{
    private const string BASE = '/api/admin/org/{orgSlug}/device-tokens';

    public function __construct(
        private readonly DeviceTokenQuery $tokens,
        private readonly CommandBus $commands,
    ) {}

    #[Route(self::BASE, name: 'api_admin_device_tokens', methods: ['GET'])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Get(
        summary: 'Device tokens of the organizer (never including the secret)',
        tags: ['devices'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'items: id, label, checkInListId, createdAt, expiresAt, revokedAt, active'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
        ],
    )]
    public function list(#[CurrentOrganizer] OrganizerView $organizer): JsonResponse
    {
        return $this->json(['items' => $this->tokens->list($organizer->id)]);
    }

    #[Route(self::BASE, name: 'api_admin_device_token_issue', methods: ['POST'])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Post(
        summary: 'Issue a device token, optionally bound to one check-in list. The secret is returned once.',
        tags: ['devices'],
        parameters: [new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['label'],
            properties: [
                new OA\Property(property: 'label', type: 'string', example: 'Gate A tablet'),
                new OA\Property(property: 'checkInListId', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'id and the token `dev1_...` (send it as `Authorization: Bearer`)'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found'),
            new OA\Response(response: 422, description: 'validation_failed or device_token_expiry_in_past'),
        ],
    )]
    public function issue(#[CurrentOrganizer] OrganizerView $organizer, #[CurrentUser] UserInterface $user, #[MapRequestPayload] IssueDeviceTokenRequest $body): JsonResponse
    {
        \assert($user instanceof IdentifiableUser);

        $result = $this->commands->dispatch(new IssueDeviceToken(
            $organizer->id,
            $body->label,
            null === $body->checkInListId ? null : CheckInListId::fromString($body->checkInListId),
            $body->expiresAt,
            UserAccountId::fromString($user->accountId()),
        ));

        $response = $this->json($result, 201);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route(self::BASE.'/{deviceTokenId}', name: 'api_admin_device_token_revoke', requirements: ['deviceTokenId' => Requirement::UUID], methods: ['DELETE'])]
    #[RequiresPermission(Permission::EventConfigure)]
    #[OA\Delete(
        summary: 'Revoke a device token (the device is locked out at once)',
        tags: ['devices'],
        parameters: [
            new OA\Parameter(name: 'orgSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'deviceTokenId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Revoked'),
            new OA\Response(response: 401, description: 'unauthorized'),
            new OA\Response(response: 403, description: 'forbidden'),
            new OA\Response(response: 404, description: 'not_found / device_token_not_found'),
        ],
    )]
    public function revoke(#[CurrentOrganizer] OrganizerView $organizer, string $deviceTokenId): Response
    {
        $this->commands->dispatch(new RevokeDeviceToken($organizer->id, $deviceTokenId));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
