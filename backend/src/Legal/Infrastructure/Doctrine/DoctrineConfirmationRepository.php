<?php

declare(strict_types=1);

namespace Eventor\Legal\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Domain\Confirmation\Confirmation;
use Eventor\Legal\Domain\Confirmation\ConfirmationRepository;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ConfirmationRepository::class)]
final readonly class DoctrineConfirmationRepository implements ConfirmationRepository
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
    ) {}

    public function append(Confirmation $confirmation): void
    {
        $this->em->persist($confirmation);
        $this->em->flush();
    }

    public function latestOfChain(LegalRequirementId $requirement, PersonId $subject, ?ParticipationId $participation, ?OrderId $order): ?array
    {
        $target = $participation?->toString() ?? $order?->toString();
        if (null === $target) {
            return null;
        }

        /** @var array{id: string, decision: string, order_id: ?string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT c.id, c.decision, c.order_id
               FROM legal.confirmation c
              WHERE c.requirement_id = :requirement
                AND c.person_id = :person
                AND COALESCE(c.participation_id, c.order_id) = :target
                AND NOT EXISTS (SELECT 1 FROM legal.confirmation n WHERE n.supersedes_id = c.id)',
            ['requirement' => $requirement->toString(), 'person' => $subject->toString(), 'target' => $target],
        );

        return false === $row ? null : ['id' => ConfirmationId::fromString($row['id']), 'decision' => ConsentDecision::from($row['decision']), 'orderId' => null === $row['order_id'] ? null : OrderId::fromString($row['order_id'])];
    }
}
