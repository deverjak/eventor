<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Port\SessionSignIn;
use Eventor\Identity\Application\Query\AccountProfileQuery;
use Eventor\Identity\Application\SignUp\SignUp;
use Eventor\Identity\UserInterface\Http\Request\SignUpRequest;
use Eventor\Shared\Application\Messaging\CommandBus;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ORG-01: public self-service registration. Creates the account, the organizer and the owner membership, then signs
 * the new owner in (session cookie). Rate limited per client address (`SignUpRateLimitListener`).
 */
#[AsController]
final class SignUpController extends AbstractController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly SessionSignIn $signIn,
        private readonly AccountProfileQuery $profiles,
    ) {}

    #[Route('/api/auth/sign-up', name: 'api_auth_sign_up', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new organizer with its owner account and sign in',
        description: 'Send the `csrf-token` header like on every unsafe request. The organizer slug becomes part of every URL.',
        tags: ['auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password', 'displayName', 'organizerName', 'slug'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 10),
                new OA\Property(property: 'displayName', type: 'string'),
                new OA\Property(property: 'organizerName', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', example: 'klub-plzen'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Registered and signed in; body is the profile (as GET /api/auth/me). Sets the EVENTOR_SESSID cookie.'),
            new OA\Response(response: 403, description: 'csrf_invalid'),
            new OA\Response(response: 409, description: 'email_already_registered or organizer_slug_taken'),
            new OA\Response(response: 422, description: 'validation_failed'),
            new OA\Response(response: 429, description: 'rate_limited'),
        ],
    )]
    public function __invoke(#[MapRequestPayload] SignUpRequest $body): JsonResponse
    {
        /** @var array{email: string} $result */
        $result = $this->commands->dispatch(new SignUp(
            $body->email,
            $body->password,
            $body->displayName,
            $body->organizerName,
            $body->slug,
        ));

        $this->signIn->signIn($result['email']);

        return $this->json($this->profiles->forUser($result['email']), 201);
    }
}
