<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\SelfService;

use Eventor\Registration\Domain\SelfService\SelfServiceToken;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The token format and its derivation (chapter 08, "Token samoobsluhy"; contracts decision 10): `ss1_<id>_<secret>`.
 * Deviation from the book: the secret is not random but `base64url(HMAC-SHA256(application secret, token id))`, so
 * `SelfServiceLinks::urlFor()` can rebuild the link whenever Communication sends a message, with nothing secret on
 * the bus. The database still keeps only the SHA-256 of the secret (a leaked table opens nothing), tokens stay
 * individually revocable, and the secret is as unguessable as the application secret.
 */
final readonly class SelfServiceTokens
{
    public const string PREFIX = 'ss1_';
    public const string SECRET_PATTERN = '[A-Za-z0-9_-]{43}';

    /** Days a token outlives the event (materials after the event). */
    public const int DAYS_AFTER_EVENT = 90;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $applicationSecret,
        #[Autowire('%eventor.public_url%')]
        private string $publicUrl,
        private ClockInterface $clock,
    ) {}

    public function issue(OrganizerId $organizer, OrderId $order, \DateTimeImmutable $eventEndsAt, string $reason): SelfServiceToken
    {
        $id = SelfServiceTokenId::generate();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return new SelfServiceToken(
            $id,
            $organizer,
            $order,
            $this->hashOf($this->secretFor($id)),
            SelfServiceToken::SCOPE_ORDER,
            $reason,
            $now,
            $eventEndsAt->modify('+'.self::DAYS_AFTER_EVENT.' days'),
        );
    }

    public function secretFor(SelfServiceTokenId $id): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'registration.self_service|'.$id->toString(), $this->applicationSecret, true)), '+/', '-_'), '=');
    }

    public function tokenFor(SelfServiceTokenId $id): string
    {
        return self::PREFIX.$id->toString().'_'.$this->secretFor($id);
    }

    /** The link the participant opens; the frontend turns it into the `Authorization: SelfService` header (chapter 08). */
    public function urlFor(SelfServiceTokenId $id): string
    {
        return rtrim($this->publicUrl, '/').'/s/'.$this->tokenFor($id);
    }

    /**
     * @return array{id: SelfServiceTokenId, secret: string}|null
     */
    public static function parse(string $token): ?array
    {
        if (1 !== preg_match('/^ss1_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_('.self::SECRET_PATTERN.')$/D', $token, $m)) {
            return null;
        }

        return ['id' => SelfServiceTokenId::fromString($m[1]), 'secret' => $m[2]];
    }

    public function hashOf(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function matches(string $secret, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hashOf($secret));
    }
}
