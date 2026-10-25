<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Invite\AcceptInvitation;
use Eventor\Identity\Application\Port\SessionSignIn;
use Eventor\Identity\Application\Query\AccountProfileQuery;
use Eventor\Identity\Application\Query\InvitationPreviewQuery;
use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\UserInterface\Http\Request\InvitationTokenRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public side of ORG-03: look at an invitation and accept it. Accepting works for a signed-in account (the invited
 * e-mail already has one, ORG-04) and anonymously with a password, which creates the account and signs it in.
 */
#[AsController]
final class InvitationAcceptanceController extends AbstractController
{
    public function __construct(
        private readonly InvitationPreviewQuery $previews,
        private readonly CommandBus $commands,
        private readonly SessionSignIn $signIn,
        private readonly AccountProfileQuery $profiles,
        private readonly Security $security,
    ) {}

    #[Route('/api/auth/invitations/preview', name: 'api_auth_invitation_preview', methods: ['POST'])]
    #[OA\Post(
        summary: 'What an invitation offers (organizer, role, whether the e-mail already has an account)',
        tags: ['auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token'], properties: [new OA\Property(property: 'token', type: 'string')])),
        responses: [
            new OA\Response(response: 200, description: 'organizerName, organizerSlug, email, role, accountExists, expiresAt'),
            new OA\Response(response: 404, description: 'invitation_not_found: unknown, expired, revoked or used'),
            new OA\Response(response: 429, description: 'rate_limited'),
        ],
    )]
    public function preview(#[MapRequestPayload] InvitationTokenRequest $body): JsonResponse
    {
        return $this->json($this->previews->preview($body->token) ?? throw InvitationNotFound::forToken());
    }

    #[Route('/api/auth/invitations/accept', name: 'api_auth_invitation_accept', methods: ['POST'])]
    #[OA\Post(
        summary: 'Accept a team invitation',
        description: 'Signed in as the invited account: only `token`. Not signed in and no account for the invited e-mail: `token`, `password` (10+ characters) and `displayName` create the account and sign in. An existing account must sign in first (409 sign_in_required).',
        tags: ['auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['token'], properties: [
            new OA\Property(property: 'token', type: 'string'),
            new OA\Property(property: 'password', type: 'string', format: 'password'),
            new OA\Property(property: 'displayName', type: 'string'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'organizerSlug, accountCreated and the profile of the signed-in account'),
            new OA\Response(response: 403, description: 'invitation_email_mismatch or csrf_invalid'),
            new OA\Response(response: 404, description: 'invitation_not_found'),
            new OA\Response(response: 409, description: 'sign_in_required or already_member'),
            new OA\Response(response: 422, description: 'validation_failed or account_details_required'),
            new OA\Response(response: 429, description: 'rate_limited'),
        ],
    )]
    public function accept(#[MapRequestPayload] InvitationTokenRequest $body): JsonResponse
    {
        $user = $this->security->getUser();

        /** @var array{organizerSlug: string, email: string, accountCreated: bool} $result */
        $result = $this->commands->dispatch(new AcceptInvitation($body->token, $user?->getUserIdentifier(), $body->password, $body->displayName));

        if (null === $user) {
            $this->signIn->signIn($result['email']);
        }

        return $this->json([
            'organizerSlug' => $result['organizerSlug'],
            'accountCreated' => $result['accountCreated'],
            'profile' => $this->profiles->forUser($result['email']),
        ]);
    }
}
