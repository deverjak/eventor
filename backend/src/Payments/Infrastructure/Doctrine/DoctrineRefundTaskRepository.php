<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Domain\Refund\RefundTask;
use Eventor\Payments\Domain\Refund\RefundTaskRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\RefundTaskId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(RefundTaskRepository::class)]
final readonly class DoctrineRefundTaskRepository implements RefundTaskRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(RefundTask $task): void
    {
        $this->em->persist($task);
    }

    public function find(RefundTaskId $id): ?RefundTask
    {
        return $this->em->find(RefundTask::class, $id);
    }

    public function lock(RefundTaskId $id): ?RefundTask
    {
        $task = $this->em->find(RefundTask::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (null !== $task) {
            $this->em->refresh($task, LockMode::PESSIMISTIC_WRITE);
        }

        return $task;
    }

    public function findOpenForOrder(OrderId $order): ?RefundTask
    {
        return $this->em->getRepository(RefundTask::class)->findOneBy(['orderId' => $order, 'status' => RefundTask::OPEN]);
    }

    public function hasOpenForOrder(OrderId $order): bool
    {
        return null !== $this->findOpenForOrder($order);
    }
}
