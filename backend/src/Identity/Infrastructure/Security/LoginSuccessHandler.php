<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Application\Query\AccountProfileQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * A successful `POST /api/auth/login` answers with the signed-in profile (same body as `GET /api/auth/me`);
 * the session cookie is set by the firewall.
 */
final readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private AccountProfileQuery $profiles,
        private NormalizerInterface $normalizer,
    ) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $profile = $this->profiles->forUser($token->getUserIdentifier());

        return new JsonResponse(null === $profile ? null : $this->normalizer->normalize($profile, 'json'));
    }
}
