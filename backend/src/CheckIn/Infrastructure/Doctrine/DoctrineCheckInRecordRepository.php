<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\CheckIn\Domain\CheckInRecord;
use Eventor\CheckIn\Domain\CheckInRecordRepository;
use Eventor\Shared\Domain\Id\CheckInId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CheckInRecordRepository::class)]
final readonly class DoctrineCheckInRecordRepository implements CheckInRecordRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(CheckInRecord $record): void
    {
        $this->em->persist($record);
    }

    public function lock(CheckInId $id): ?CheckInRecord
    {
        return $this->em->find(CheckInRecord::class, $id, LockMode::PESSIMISTIC_WRITE);
    }

    public function validFor(ParticipationId $participation): ?CheckInRecord
    {
        $record = $this->em->createQueryBuilder()
            ->select('c')->from(CheckInRecord::class, 'c')
            ->where('c.participationId = :participation')->andWhere('c.voidedAt IS NULL')
            ->setParameter('participation', $participation, 'participation_id')
            ->getQuery()->getOneOrNullResult();
        \assert(null === $record || $record instanceof CheckInRecord);

        return $record;
    }

    public function lockParticipation(ParticipationId $participation): void
    {
        // Transaction-scoped: released at COMMIT / ROLLBACK together with everything else the handler did.
        $this->em->getConnection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'checkin:'.$participation->toString()]);
    }

    public function flush(): void
    {
        $this->em->flush();
    }
}
