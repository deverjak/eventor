<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Registration\Contract\Exception\OrderNotFound;
use Eventor\Registration\Domain\Order\Order;
use Eventor\Registration\Domain\Order\OrderRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(OrderRepository::class)]
final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(OrderId $id): Order
    {
        return $this->em->getRepository(Order::class)->findOneBy(['id' => $id]) ?? throw OrderNotFound::forId($id);
    }

    public function add(Order $order): void
    {
        $this->em->persist($order);
    }
}
