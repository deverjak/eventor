<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Registration\Contract\Exception\PersonNotFound;
use Eventor\Registration\Domain\Person\Person;
use Eventor\Registration\Domain\Person\PersonRepository;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PersonId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PersonRepository::class)]
final readonly class DoctrinePersonRepository implements PersonRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function get(PersonId $id): Person
    {
        return $this->em->getRepository(Person::class)->findOneBy(['id' => $id]) ?? throw PersonNotFound::forId($id);
    }

    public function ofOrder(OrderId $order): array
    {
        return array_values($this->em->getRepository(Person::class)->findBy(['orderId' => $order], ['id' => 'ASC']));
    }

    public function add(Person $person): void
    {
        $this->em->persist($person);
    }
}
