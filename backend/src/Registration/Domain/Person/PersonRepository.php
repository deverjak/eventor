<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Person;

use Eventor\Registration\Contract\Exception\PersonNotFound;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\PersonId;

/**
 * People of the active tenant.
 */
interface PersonRepository
{
    /**
     * @throws PersonNotFound
     */
    public function get(PersonId $id): Person;

    /**
     * @return list<Person>
     */
    public function ofOrder(OrderId $order): array;

    public function add(Person $person): void;
}
