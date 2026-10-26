<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Registration\Domain\SelfService\SelfServiceToken;
use Eventor\Registration\Domain\SelfService\SelfServiceTokenRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(SelfServiceTokenRepository::class)]
final readonly class DoctrineSelfServiceTokenRepository implements SelfServiceTokenRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function usableOfOrder(OrderId $order, \DateTimeImmutable $now): ?SelfServiceToken
    {
        $found = $this->em->createQueryBuilder()
            ->select('t')->from(SelfServiceToken::class, 't')
            ->where('t.orderId = :order AND t.revokedAt IS NULL AND t.expiresAt > :now')
            ->setParameter('order', $order, 'order_id')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->orderBy('t.issuedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getResult();
        $token = \is_array($found) ? ($found[0] ?? null) : null;

        return $token instanceof SelfServiceToken ? $token : null;
    }

    public function add(SelfServiceToken $token): void
    {
        $this->em->persist($token);
    }
}
