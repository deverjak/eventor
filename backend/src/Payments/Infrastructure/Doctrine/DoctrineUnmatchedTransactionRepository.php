<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransaction;
use Eventor\Payments\Domain\Unmatched\UnmatchedTransactionRepository;
use Eventor\Shared\Domain\Id\BankTransactionId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UnmatchedTransactionRepository::class)]
final readonly class DoctrineUnmatchedTransactionRepository implements UnmatchedTransactionRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(UnmatchedTransaction $transaction): void
    {
        $this->em->persist($transaction);
    }

    public function find(BankTransactionId $id): ?UnmatchedTransaction
    {
        return $this->em->find(UnmatchedTransaction::class, $id);
    }

    public function lockByPairrotId(string $pairrotTxId): ?UnmatchedTransaction
    {
        $transaction = $this->em->getRepository(UnmatchedTransaction::class)->findOneBy(['pairrotTxId' => $pairrotTxId]);
        if (null !== $transaction) {
            $this->em->refresh($transaction, LockMode::PESSIMISTIC_WRITE);
        }

        return $transaction;
    }
}
