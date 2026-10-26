<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Registration\Domain\Answer\AnswerSet;
use Eventor\Registration\Domain\Answer\AnswerSetRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AnswerSetRepository::class)]
final readonly class DoctrineAnswerSetRepository implements AnswerSetRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
    ) {}

    public function current(string $scope, string $subjectId): ?AnswerSet
    {
        $found = $this->em->createQueryBuilder()
            ->select('a')->from(AnswerSet::class, 'a')
            ->where('a.scope = :scope AND a.subjectId = :subject AND a.supersededById IS NULL')
            ->setParameter('scope', $scope)->setParameter('subject', $subjectId)
            ->getQuery()->getResult();
        $set = \is_array($found) ? ($found[0] ?? null) : null;

        return $set instanceof AnswerSet ? $set : null;
    }

    public function currentOfOrder(OrderId $order): array
    {
        $found = $this->em->createQueryBuilder()
            ->select('a')->from(AnswerSet::class, 'a')
            ->where('a.orderId = :order AND a.supersededById IS NULL')
            ->setParameter('order', $order, 'order_id')
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()->getResult();

        return \is_array($found) ? array_values(array_filter($found, static fn(mixed $set): bool => $set instanceof AnswerSet)) : [];
    }

    public function add(AnswerSet $set): void
    {
        $this->em->persist($set);
    }

    public function replace(AnswerSet $old, AnswerSet $new): void
    {
        // The foreign key to the new row is deferred; the old row stops being "current" before the new one is inserted.
        $this->connection->executeStatement(
            'UPDATE registration.answer_set SET superseded_by_id = :new WHERE id = :old AND superseded_by_id IS NULL',
            ['new' => $new->id()->toString(), 'old' => $old->id()->toString()],
        );
        $this->em->persist($new);
    }
}
