<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Infrastructure\Serializer\ProblemNormalizer;
use Eventor\Shared\UserInterface\Http\Problem\Problem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * A bad, expired or revoked device token: always the same `401 invalid_device_token` problem.
 */
final readonly class DeviceAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private ProblemNormalizer $normalizer,
        private AuditContext $context,
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $problem = new Problem('invalid_device_token', 401, 'Invalid device token', 'The device token is invalid, expired or revoked.', correlationId: $this->context->correlationId());

        return new JsonResponse($this->normalizer->normalize($problem), 401, ['Content-Type' => 'application/problem+json', 'WWW-Authenticate' => 'Bearer']);
    }
}
