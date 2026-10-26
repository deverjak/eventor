<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\SelfService;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Application\Query\Cells;
use Eventor\Registration\Contract\Exception\InvalidQrToken;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\ParticipationQrTokens;
use Eventor\Registration\Contract\VerifiedQr;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * `qr1.<payload>.<sig>` (chapter 08): the payload is base64url of the 16 bytes of the participation id plus the u16
 * `qr_version` and a u8 key id (0), the signature is HMAC-SHA256 truncated to 128 bits. No personal data in the code.
 * Cancelling bumps `qr_version`, so old codes die without a revocation list (CHK-03).
 */
#[AsAlias(ParticipationQrTokens::class)]
final readonly class HmacParticipationQrTokens implements ParticipationQrTokens
{
    public function __construct(
        private Connection $connection,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {}

    public function issue(OrganizerId $organizer, ParticipationId $id): string
    {
        $version = $this->connection->fetchOne(
            'SELECT qr_version FROM registration.participation WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );
        if (false === $version) {
            throw ParticipationNotFound::forId($id);
        }

        $payload = $id->toUuid()->toBinary().pack('nC', Cells::int($version), 0);

        return 'qr1.'.self::encode($payload).'.'.self::encode($this->signature($payload));
    }

    public function verify(OrganizerId $organizer, string $token): VerifiedQr
    {
        $parts = explode('.', $token);
        if (3 !== \count($parts) || 'qr1' !== $parts[0]) {
            throw InvalidQrToken::badSignature();
        }

        $payload = self::decode($parts[1]);
        $signature = self::decode($parts[2]);
        if (19 !== \strlen($payload) || !hash_equals($this->signature($payload), $signature)) {
            throw InvalidQrToken::badSignature();
        }

        $id = ParticipationId::fromString(\Symfony\Component\Uid\Uuid::fromBinary(substr($payload, 0, 16))->toRfc4122());
        /** @var array{v: int} $unpacked */
        $unpacked = unpack('nv', substr($payload, 16, 2));

        $current = $this->connection->fetchOne(
            'SELECT qr_version FROM registration.participation WHERE organizer_id = :organizer AND id = :id',
            ['organizer' => $organizer->toString(), 'id' => $id->toString()],
        );
        if (false === $current) {
            throw InvalidQrToken::badSignature();
        }
        if (Cells::int($current) !== $unpacked['v']) {
            throw InvalidQrToken::outdated();
        }

        return new VerifiedQr($id, $unpacked['v']);
    }

    private function signature(string $payload): string
    {
        return substr(hash_hmac('sha256', 'qr1|'.$payload, $this->secret, true), 0, 16);
    }

    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function decode(string $text): string
    {
        $decoded = base64_decode(strtr($text, '-_', '+/'), true);

        return false === $decoded ? '' : $decoded;
    }
}
