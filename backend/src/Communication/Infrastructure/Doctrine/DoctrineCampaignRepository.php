<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Communication\Domain\Campaign\Campaign;
use Eventor\Communication\Domain\Campaign\CampaignRepository;
use Eventor\Shared\Domain\Id\CampaignId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CampaignRepository::class)]
final readonly class DoctrineCampaignRepository implements CampaignRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(Campaign $campaign): void
    {
        $this->em->persist($campaign);
    }

    public function find(CampaignId $id): ?Campaign
    {
        return $this->em->getRepository(Campaign::class)->findOneBy(['id' => $id]);
    }

    public function findForUpdate(CampaignId $id): ?Campaign
    {
        $campaign = $this->em->createQuery('SELECT c FROM '.Campaign::class.' c WHERE c.id = :id')
            ->setParameter('id', $id, 'campaign_id')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
        \assert(null === $campaign || $campaign instanceof Campaign);

        return $campaign;
    }

    public function findByProposalKey(string $proposalKey): ?Campaign
    {
        return $this->em->getRepository(Campaign::class)->findOneBy(['proposalKey' => $proposalKey]);
    }
}
