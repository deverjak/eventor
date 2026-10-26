<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Registration\Application\SelfService\SelfServiceTokens;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Contract\SelfServiceLinks;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The self-service URL of an order (REG-08): derived from the newest usable token of the order, so a message can carry
 * the link at any time while no secret is stored or sent (contracts decision 10). An order whose tokens are all
 * revoked or expired has no link until a new one is requested (REG-11).
 */
#[AsAlias(SelfServiceLinks::class)]
final readonly class SelfServiceLinksQuery implements SelfServiceLinks
{
    public function __construct(
        private Connection $connection,
        private SelfServiceTokens $tokens,
        private ClockInterface $clock,
    ) {}

    public function urlFor(OrganizerId $organizer, OrderId $order): string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM registration.self_service_token
              WHERE organizer_id = :organizer AND order_id = :order AND revoked_at IS NULL AND expires_at > :now
              ORDER BY issued_at DESC, id DESC LIMIT 1',
            ['organizer' => $organizer->toString(), 'order' => $order->toString(), 'now' => \DateTimeImmutable::createFromInterface($this->clock->now())->format('Y-m-d H:i:sP')],
        );
        if (false === $id) {
            throw OrderNotFound::forId($order);
        }

        return $this->tokens->urlFor(SelfServiceTokenId::fromString(Cells::string($id)));
    }
}
