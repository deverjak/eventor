<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Query\AccountProfileQuery;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[AsController]
final class MeController extends AbstractController
{
    public function __construct(private readonly AccountProfileQuery $profiles) {}

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Get(
        summary: 'Who am I: the signed-in member and the organizers they belong to',
        tags: ['auth'],
        responses: [
            new OA\Response(response: 200, description: 'Profile with memberships'),
            new OA\Response(response: 401, description: 'unauthorized'),
        ],
    )]
    public function __invoke(#[CurrentUser] UserInterface $user): JsonResponse
    {
        $profile = $this->profiles->forUser($user->getUserIdentifier());
        if (null === $profile) {
            throw $this->createAccessDeniedException();
        }

        return $this->json($profile);
    }
}
