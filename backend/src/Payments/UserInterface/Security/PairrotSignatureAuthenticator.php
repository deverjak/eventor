<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Security;

use Eventor\Payments\Application\Webhook\PairrotWebhookSignature;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Firewall `webhooks` (ADR 006, chapter 04 path 4): the caller proves itself with the HMAC of the raw body, not with a
 * cookie or a key. HMAC-SHA256 over `"<Pairrot-Webhook-Id>.<Pairrot-Webhook-Timestamp>." + raw body`, within 5 minutes
 * of our clock, compared in constant time against EVERY configured secret (two during a rotation) and every entry of
 * the signature header. A failure is a 401, which Pairrot does not retry (correct: the request is not from Pairrot).
 */
final class PairrotSignatureAuthenticator extends AbstractAuthenticator
{
    /**
     * @param array<string, string> $keys key id => raw key bytes (`%env(pairrot_keys:PAIRROT_WEBHOOK_SECRETS)%`)
     */
    public function __construct(
        #[Autowire('%env(pairrot_keys:PAIRROT_WEBHOOK_SECRETS)%')]
        private readonly array $keys,
        private readonly ClockInterface $clock,
    ) {}

    public function supports(Request $request): bool
    {
        return true; // the firewall pattern already limits this authenticator to /api/webhooks
    }

    public function authenticate(Request $request): Passport
    {
        $valid = PairrotWebhookSignature::verify(
            $this->keys,
            (string) $request->headers->get('Pairrot-Webhook-Id', ''),
            (string) $request->headers->get('Pairrot-Webhook-Timestamp', ''),
            (string) $request->headers->get('Pairrot-Webhook-Signature', ''),
            $request->getContent(),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        if (!$valid) {
            throw new CustomUserMessageAuthenticationException('The webhook signature is missing, wrong or outside the time window.');
        }

        return new SelfValidatingPassport(new UserBadge('pairrot', static fn(): InMemoryUser => new InMemoryUser('pairrot', null, ['ROLE_PAIRROT'])));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => 'Authentication required',
                'status' => 401,
                'code' => 'invalid_signature',
                'detail' => 'The webhook signature is missing, wrong or outside the time window.',
            ],
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
