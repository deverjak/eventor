<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Recover;

use Doctrine\DBAL\Connection;
use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\Exception\EventNotFound;
use Eventor\Registration\Application\SelfService\SelfServiceTokens;
use Eventor\Registration\Domain\SelfService\SelfServiceTokenRepository;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('An unknown address matches no order and records nothing; each match records the link request.')]
final readonly class RequestSelfServiceLinkHandler
{
    public const int MAX_ORDERS = 20;

    public function __construct(
        private Connection $connection,
        private SelfServiceTokenRepository $tokenRepository,
        private SelfServiceTokens $tokens,
        private EventCatalog $catalog,
        private ClockInterface $clock,
    ) {}

    public function __invoke(RequestSelfServiceLink $command): int
    {
        $sql = 'SELECT id, event_id FROM registration.orders WHERE organizer_id = :organizer AND lower(orderer_email) = lower(:email)';
        $params = ['organizer' => $command->organizerId->toString(), 'email' => trim($command->email)];
        if (null !== $command->eventId) {
            $sql .= ' AND event_id = :event';
            $params['event'] = $command->eventId->toString();
        }

        /** @var list<array{id: string, event_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql.' ORDER BY placed_at DESC LIMIT '.self::MAX_ORDERS, $params);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $requested = 0;
        foreach ($rows as $row) {
            $order = OrderId::fromString($row['id']);
            $token = $this->tokenRepository->usableOfOrder($order, $now);
            if (null === $token) {
                try {
                    $ends = $this->catalog->summary($command->organizerId, EventId::fromString($row['event_id']))->endsAt;
                } catch (EventNotFound) {
                    continue;
                }
                $token = $this->tokens->issue($command->organizerId, $order, $ends < $now ? $now : $ends, 'recovery');
                $this->tokenRepository->add($token);
            }
            $token->noteLinkRequested();
            ++$requested;
        }

        return $requested;
    }
}
