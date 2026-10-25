<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Money;
use Eventor\Tests\Fixtures\Doctrine\PricedItem;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Money (embeddable), typed ids (custom DBAL types) and the schema layout against a real Postgres.
 */
final class DoctrineMappingTest extends IntegrationTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        // The temporary table lives only inside this test's transaction (dama rolls DDL back as well).
        $this->connection()->executeStatement(
            'CREATE TEMPORARY TABLE priced_item (id uuid PRIMARY KEY, organizer_id uuid NOT NULL, price_minor bigint NOT NULL, price_currency char(3) NOT NULL)',
        );
        $this->em->getFilters()->disable('organizer');
    }

    public function test_money_is_stored_as_bigint_minor_units_and_a_currency_code(): void
    {
        $organizer = OrganizerId::generate();
        $id = Uuid::v7()->toRfc4122();
        $this->em->persist(new PricedItem($id, $organizer, Money::of(123_456_789_012, Currency::EUR)));
        $this->em->flush();

        $row = $this->connection()->fetchAssociative('SELECT price_minor, price_currency, organizer_id FROM priced_item WHERE id = ?', [$id]);
        self::assertSame(['price_minor' => 123_456_789_012, 'price_currency' => 'EUR', 'organizer_id' => $organizer->toString()], $row);

        $this->em->clear();
        $loaded = $this->em->find(PricedItem::class, $id);
        self::assertInstanceOf(PricedItem::class, $loaded);
        self::assertTrue($loaded->price()->equals(Money::of(123_456_789_012, Currency::EUR)));
        self::assertTrue($organizer->equals($loaded->organizerId()));
        self::assertIsInt($loaded->price()->minor);
    }

    public function test_the_database_refuses_a_malformed_currency_length(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception::class);

        $this->connection()->executeStatement(
            "INSERT INTO priced_item (id, organizer_id, price_minor, price_currency) VALUES (?, ?, 1, 'TOOLONG')",
            [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()],
        );
    }

    public function test_a_typed_id_column_is_a_native_postgres_uuid(): void
    {
        $type = $this->connection()->fetchOne(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = 'identity' AND table_name = 'organizer' AND column_name = 'id'",
        );

        self::assertSame('uuid', $type);
    }
}
