<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Domain\Request\PaymentRequest;
use Eventor\Payments\Domain\Request\PaymentRequestRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PaymentRequestRepository::class)]
final readonly class DoctrinePaymentRequestRepository implements PaymentRequestRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(PaymentRequest $request): void
    {
        $this->em->persist($request);
    }

    public function find(PaymentRequestId $id): ?PaymentRequest
    {
        return $this->em->find(PaymentRequest::class, $id);
    }

    public function lock(PaymentRequestId $id): ?PaymentRequest
    {
        $request = $this->em->find(PaymentRequest::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (null !== $request) {
            $this->em->refresh($request, LockMode::PESSIMISTIC_WRITE);
        }

        return $request;
    }

    public function lockByPairrotReference(string $pairrotId, ?string $externalId): ?PaymentRequest
    {
        $repository = $this->em->getRepository(PaymentRequest::class);
        $request = $repository->findOneBy(['pairrotId' => $pairrotId]);
        if (null === $request && null !== $externalId) {
            $request = $repository->findOneBy(['externalId' => $externalId]);
        }
        if (null !== $request) {
            $this->em->refresh($request, LockMode::PESSIMISTIC_WRITE);
        }

        return $request;
    }

    public function findByOrderAndAttempt(OrderId $order, int $attempt): ?PaymentRequest
    {
        return $this->em->getRepository(PaymentRequest::class)->findOneBy(['orderId' => $order, 'attempt' => $attempt]);
    }

    public function forOrder(OrderId $order): array
    {
        return array_values($this->em->getRepository(PaymentRequest::class)->findBy(['orderId' => $order], ['attempt' => 'ASC']));
    }

    public function highestAttempt(OrderId $order): int
    {
        $max = $this->em->createQueryBuilder()
            ->select('MAX(r.attempt)')->from(PaymentRequest::class, 'r')
            ->where('r.orderId = :order')->setParameter('order', $order, 'order_id')
            ->getQuery()->getSingleScalarResult();

        return is_numeric($max) ? (int) $max : 0;
    }
}
