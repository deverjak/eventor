<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class IdTest extends TestCase
{
    public function test_generated_ids_are_uuid_v7_and_time_ordered(): void
    {
        $first = OrganizerId::generate();
        usleep(2_000);
        $second = OrganizerId::generate();

        self::assertInstanceOf(\Symfony\Component\Uid\UuidV7::class, $first->toUuid());
        self::assertLessThan(0, strcmp($first->toString(), $second->toString()));
    }

    public function test_it_round_trips_through_a_string(): void
    {
        $id = OrganizerId::generate();

        self::assertTrue($id->equals(OrganizerId::fromString($id->toString())));
        self::assertSame($id->toString(), (string) $id);
        self::assertSame('"'.$id->toString().'"', json_encode($id, \JSON_THROW_ON_ERROR));
    }

    public function test_it_normalises_case(): void
    {
        $upper = strtoupper(Uuid::v7()->toRfc4122());

        self::assertSame(strtolower($upper), OrganizerId::fromString($upper)->toString());
    }

    public function test_different_id_types_never_equal_each_other(): void
    {
        $uuid = Uuid::v7()->toRfc4122();

        self::assertFalse(OrganizerId::fromString($uuid)->equals(UserAccountId::fromString($uuid)));
    }

    public function test_it_rejects_malformed_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrganizerId::fromString('not-a-uuid');
    }
}
