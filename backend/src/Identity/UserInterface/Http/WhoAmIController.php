<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Http;

use Eventor\Identity\Application\Query\AccountProfileQuery;
use Eventor\Identity\Contract\DevicePrincipal;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Proves the `check_in` firewall: a device token (`Authorization: Bearer dev1_...`) or a team session both reach it.
 * The CheckIn module builds the real endpoints on the same firewall.
 */
#[AsController]
final class WhoAmIController extends AbstractController
{
    public function __construct(private readonly AccountProfileQuery $profiles) {}

    #[Route('/api/check-in/whoami', name: 'api_check_in_whoami', methods: ['GET'])]
    #[OA\Get(
        summary: 'Who is calling the check-in API: a device (organizer, check-in list) or a team member',
        tags: ['devices'],
        responses: [
            new OA\Response(response: 200, description: '`type` is `device` (deviceTokenId, organizerId, checkInListId, label) or `member` (the profile)'),
            new OA\Response(response: 401, description: 'unauthorized / invalid_device_token'),
        ],
    )]
    public function __invoke(#[CurrentUser] UserInterface $user): JsonResponse
    {
        if ($user instanceof DevicePrincipal) {
            return $this->json([
                'type' => 'device',
                'deviceTokenId' => $user->deviceTokenId(),
                'organizerId' => $user->organizerId(),
                'checkInListId' => $user->checkInListId(),
                'label' => $user->label(),
            ]);
        }

        return $this->json(['type' => 'member', 'profile' => $this->profiles->forUser($user->getUserIdentifier())]);
    }
}
