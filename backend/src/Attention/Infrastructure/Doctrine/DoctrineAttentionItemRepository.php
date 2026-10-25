<?php

declare(strict_types=1);

namespace Eventor\Attention\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Attention\Contract\AttentionState;
use Eventor\Attention\Domain\AttentionItem;
use Eventor\Attention\Domain\AttentionItemRepository;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AttentionItemRepository::class)]
final readonly class DoctrineAttentionItemRepository implements AttentionItemRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(AttentionItem $item): void
    {
        $this->em->persist($item);
    }

    public function findOfOrganizer(AttentionItemId $id, OrganizerId $organizer): ?AttentionItem
    {
        return $this->em->getRepository(AttentionItem::class)->findOneBy(['id' => $id, 'organizerId' => $organizer]);
    }

    public function findUnresolvedByDedupeKey(string $dedupeKey): ?AttentionItem
    {
        return $this->em->getRepository(AttentionItem::class)->findOneBy([
            'dedupeKey' => $dedupeKey,
            'state' => [AttentionState::Open, AttentionState::Acknowledged],
        ]);
    }
}
