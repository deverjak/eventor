<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Documents and reserves the route of the firewall's `json_login`. The request never reaches this method: the
 * authenticator answers it (success: the profile of the member, failure: 401/429 problem details).
 */
#[AsController]
final class LoginController extends AbstractController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Sign in a team member (session cookie)',
        description: 'Send the `csrf-token` header (any random token of 24+ characters) like on every unsafe request.',
        tags: ['auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        )),
        responses: [
            new OA\Response(response: 200, description: 'Signed in; body is the profile (as GET /api/auth/me). Sets the EVENTOR_SESSID cookie.'),
            new OA\Response(response: 401, description: 'invalid_credentials'),
            new OA\Response(response: 403, description: 'csrf_invalid'),
            new OA\Response(response: 429, description: 'rate_limited'),
        ],
    )]
    public function __invoke(): never
    {
        throw new \LogicException('Handled by the json_login authenticator of the "team" firewall.');
    }
}
