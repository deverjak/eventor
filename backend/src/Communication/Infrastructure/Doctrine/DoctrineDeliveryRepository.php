<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Communication\Contract\DeliveryState;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\CampaignId;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Id\OrderId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(DeliveryRepository::class)]
final readonly class DoctrineDeliveryRepository implements DeliveryRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(Delivery $delivery): void
    {
        $this->em->persist($delivery);
    }

    public function find(DeliveryId $id): ?Delivery
    {
        return $this->em->getRepository(Delivery::class)->findOneBy(['id' => $id]);
    }

    public function findForUpdate(DeliveryId $id): ?Delivery
    {
        $delivery = $this->em->createQuery('SELECT d FROM '.Delivery::class.' d WHERE d.id = :id')
            ->setParameter('id', $id, 'delivery_id')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
        \assert(null === $delivery || $delivery instanceof Delivery);

        if (null !== $delivery) {
            $this->em->refresh($delivery);
        }

        return $delivery;
    }

    public function existsWithKey(string $idempotencyKey): bool
    {
        return null !== $this->em->getRepository(Delivery::class)->findOneBy(['idempotencyKey' => $idempotencyKey]);
    }

    public function failed(?CampaignId $campaign): array
    {
        $criteria = ['state' => DeliveryState::Failed];
        if (null !== $campaign) {
            $criteria['campaignId'] = $campaign;
        }

        return array_values($this->em->getRepository(Delivery::class)->findBy($criteria, ['createdAt' => 'ASC']));
    }

    public function waitingForActivity(ActivityId $activity, string $trigger): array
    {
        return array_values($this->em->getRepository(Delivery::class)->findBy(['activityId' => $activity, 'trigger' => $trigger, 'state' => DeliveryState::Scheduled]));
    }

    public function waitingMarketingOfOrder(OrderId $order): array
    {
        return array_values(array_filter(
            $this->em->getRepository(Delivery::class)->findBy(['orderId' => $order, 'state' => DeliveryState::Scheduled]),
            static fn(Delivery $delivery): bool => \Eventor\Communication\Contract\CampaignPurpose::Marketing === $delivery->purpose(),
        ));
    }

    public function progressOfCampaign(CampaignId $campaign, DeliveryId $except): array
    {
        /** @var list<array{state: DeliveryState, n: int<0, max>}> $rows */
        $rows = $this->em->createQuery('SELECT d.state AS state, COUNT(d.id) AS n FROM '.Delivery::class.' d WHERE d.campaignId = :campaign AND d.id <> :except GROUP BY d.state')
            ->setParameter('campaign', $campaign, 'campaign_id')
            ->setParameter('except', $except, 'delivery_id')
            ->getResult();

        $progress = ['waiting' => 0, 'sent' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $count = (int) $row['n'];
            match ($row['state']) {
                DeliveryState::Scheduled => $progress['waiting'] += $count,
                DeliveryState::Sent, DeliveryState::Delivered => $progress['sent'] += $count,
                DeliveryState::Skipped => $progress['skipped'] += $count,
                DeliveryState::Failed => null,
            };
        }

        return $progress;
    }
}
