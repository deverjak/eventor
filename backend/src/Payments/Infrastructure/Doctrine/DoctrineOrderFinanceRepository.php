<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Domain\Finance\OrderFinance;
use Eventor\Payments\Domain\Finance\OrderFinanceRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OrderFinanceRepository::class)]
final readonly class DoctrineOrderFinanceRepository implements OrderFinanceRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(OrderFinance $finance): void
    {
        $this->em->persist($finance);
    }

    public function find(OrderId $order): ?OrderFinance
    {
        return $this->em->find(OrderFinance::class, $order);
    }

    public function lock(OrderId $order): ?OrderFinance
    {
        $finance = $this->em->find(OrderFinance::class, $order, LockMode::PESSIMISTIC_WRITE);
        if (null !== $finance) {
            $this->em->refresh($finance, LockMode::PESSIMISTIC_WRITE);
        }

        return $finance;
    }
}
