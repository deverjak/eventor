<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Domain\Participation\ParticipationRepository;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ParticipationRepository::class)]
final readonly class DoctrineParticipationRepository implements ParticipationRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(ParticipationId $id): Participation
    {
        return $this->em->getRepository(Participation::class)->findOneBy(['id' => $id]) ?? throw ParticipationNotFound::forId($id);
    }

    public function ofOrder(OrderId $order): array
    {
        return array_values($this->em->getRepository(Participation::class)->findBy(['orderId' => $order], ['createdAt' => 'ASC', 'id' => 'ASC']));
    }

    public function activeOfEvent(EventId $event): array
    {
        return array_values($this->em->getRepository(Participation::class)->findBy(
            ['eventId' => $event, 'state' => [ParticipationState::Pending->value, ParticipationState::Confirmed->value, ParticipationState::RequiresAttention->value]],
            ['createdAt' => 'ASC', 'id' => 'ASC'],
        ));
    }

    public function ofPerson(PersonId $person): array
    {
        return array_values($this->em->getRepository(Participation::class)->findBy(['personId' => $person], ['createdAt' => 'ASC', 'id' => 'ASC']));
    }

    public function add(Participation $participation): void
    {
        $this->em->persist($participation);
    }
}
