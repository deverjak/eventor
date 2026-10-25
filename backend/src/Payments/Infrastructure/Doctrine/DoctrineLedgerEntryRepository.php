<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Payments\Domain\Ledger\LedgerEntry;
use Eventor\Payments\Domain\Ledger\LedgerEntryKind;
use Eventor\Payments\Domain\Ledger\LedgerEntryRepository;
use Eventor\Payments\Domain\Ledger\LedgerSource;
use Eventor\Shared\Domain\Id\LedgerEntryId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PaymentRequestId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(LedgerEntryRepository::class)]
final readonly class DoctrineLedgerEntryRepository implements LedgerEntryRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(LedgerEntry $entry): void
    {
        $this->em->persist($entry);
    }

    public function find(LedgerEntryId $id): ?LedgerEntry
    {
        return $this->em->find(LedgerEntry::class, $id);
    }

    public function forOrder(OrderId $order): array
    {
        /** @var list<LedgerEntry> $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('e')->from(LedgerEntry::class, 'e')
            ->where('e.orderId = :order')->setParameter('order', $order, 'order_id')
            ->orderBy('e.recordedAt', 'ASC')->addOrderBy('e.id', 'ASC')
            ->getQuery()->getResult();

        return $entries;
    }

    public function findBySourceRef(LedgerSource $source, string $sourceRef): ?LedgerEntry
    {
        return $this->em->getRepository(LedgerEntry::class)->findOneBy(['source' => $source, 'sourceRef' => $sourceRef]);
    }

    public function latestAllocationOfRequest(PaymentRequestId $request): ?LedgerEntry
    {
        return $this->em->getRepository(LedgerEntry::class)->findOneBy(
            ['paymentRequestId' => $request, 'kind' => LedgerEntryKind::PairrotAllocation],
            ['recordedAt' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function findCorrectionOf(LedgerEntryId $original): ?LedgerEntry
    {
        return $this->em->getRepository(LedgerEntry::class)->findOneBy(['compensatesId' => $original, 'kind' => LedgerEntryKind::CashCorrection]);
    }
}
