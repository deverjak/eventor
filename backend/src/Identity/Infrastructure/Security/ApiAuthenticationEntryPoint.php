<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Infrastructure\Serializer\ProblemNormalizer;
use Eventor\Shared\UserInterface\Http\Problem\ProblemFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Anonymous access to a protected API route: a JSON problem `401 unauthorized`, never a redirect to a login page.
 */
final readonly class ApiAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ProblemFactory $problems,
        private ProblemNormalizer $normalizer,
        private AuditContext $context,
    ) {}

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $problem = $this->problems->fromThrowable(
            new UnauthorizedHttpException('Cookie', 'Authentication is required. Sign in at POST /api/auth/login.'),
            $this->context->correlationId(),
        );

        return new JsonResponse($this->normalizer->normalize($problem), $problem->status, ['Content-Type' => 'application/problem+json']);
    }
}
