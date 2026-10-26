<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Security;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Application\Query\Cells;
use Eventor\Registration\Application\SelfService\SelfServiceTokens;
use Eventor\Registration\Contract\Exception\SelfServiceTokenRejected;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Firewall `self_service` (API-04, ADR 006): `Authorization: SelfService ss1_<id>_<secret>`. The token is looked up by its
 * id, the secret is compared in constant time with the stored SHA-256, revoked and expired tokens are refused with a
 * pointer to the link recovery (REG-11). The token never appears in a URL, a log or a response.
 */
final class SelfServiceTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string SCHEME = 'SelfService ';

    public function __construct(
        private readonly Connection $connection,
        private readonly SelfServiceTokens $tokens,
        private readonly ClockInterface $clock,
    ) {}

    public function supports(Request $request): bool
    {
        return true; // the firewall pattern limits this authenticator to /api/self-service; no header is a 401, not "anonymous"
    }

    public function authenticate(Request $request): Passport
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, self::SCHEME)) {
            throw new CustomUserMessageAuthenticationException(SelfServiceTokenRejected::missing()->getMessage());
        }

        $parsed = SelfServiceTokens::parse(trim(substr($header, \strlen(self::SCHEME))));
        if (null === $parsed) {
            throw new CustomUserMessageAuthenticationException(SelfServiceTokenRejected::missing()->getMessage());
        }

        /** @var array<string, mixed>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT organizer_id, order_id, secret_sha256, expires_at, revoked_at FROM registration.self_service_token WHERE id = :id',
            ['id' => $parsed['id']->toString()],
        );
        if (false === $row || !$this->tokens->matches($parsed['secret'], Cells::string($row['secret_sha256']))) {
            throw new CustomUserMessageAuthenticationException(SelfServiceTokenRejected::unknown()->getMessage());
        }
        if (null !== $row['revoked_at'] || Cells::time($row['expires_at']) <= \DateTimeImmutable::createFromInterface($this->clock->now())) {
            throw new CustomUserMessageAuthenticationException(SelfServiceTokenRejected::expired()->getMessage());
        }

        $principal = new SelfServicePrincipal($parsed['id'], OrganizerId::fromString(Cells::string($row['organizer_id'])), OrderId::fromString(Cells::string($row['order_id'])));

        return new SelfValidatingPassport(new UserBadge($principal->getUserIdentifier(), static fn(): SelfServicePrincipal => $principal));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->problem($exception->getMessageKey());
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->problem(SelfServiceTokenRejected::missing()->getMessage());
    }

    private function problem(string $detail): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'https://eventor.cz/problems/token-invalid',
                'title' => 'The self-service link is not valid',
                'status' => 401,
                'code' => 'token_invalid',
                'detail' => $detail,
                'nextSteps' => [['action' => 'recover_self_service_link', 'href' => '/api/public/self-service/recover']],
            ],
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
