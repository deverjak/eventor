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
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * The answer to a failed login is always the same, whether the account exists or the password is wrong
 * (chapter 08): `401 invalid_credentials`. Throttled attempts get `429 rate_limited`.
 */
final readonly class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private ProblemNormalizer $normalizer,
        private AuditContext $context,
    ) {}

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $problem = new Problem('rate_limited', 429, 'Too many requests', 'Too many sign-in attempts. Try again later.', correlationId: $this->context->correlationId());
            $response = new JsonResponse($this->normalizer->normalize($problem), 429, ['Content-Type' => 'application/problem+json']);
            $minutes = $exception->getMessageData()['%minutes%'] ?? null;
            if (is_numeric($minutes)) {
                $response->headers->set('Retry-After', (string) ((int) $minutes * 60));
            }

            return $response;
        }

        $problem = new Problem('invalid_credentials', 401, 'Invalid credentials', 'The e-mail or password is not correct.', correlationId: $this->context->correlationId());

        return new JsonResponse($this->normalizer->normalize($problem), 401, ['Content-Type' => 'application/problem+json']);
    }
}
