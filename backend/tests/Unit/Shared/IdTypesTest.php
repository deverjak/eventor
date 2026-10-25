<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Eventor\Shared\Domain\Id\AbstractId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Infrastructure\Doctrine\OrderIdType;
use Eventor\Shared\Infrastructure\Doctrine\SharedIdTypes;
use PHPUnit\Framework\TestCase;

/**
 * Every typed id of `Shared\Domain\Id` has a DBAL type registered by the Shared module, so no module has to register
 * (or forgets to register) the ids it maps. A new id without a type fails here.
 */
final class IdTypesTest extends TestCase
{
    public function test_every_shared_id_class_has_exactly_one_registered_dbal_type(): void
    {
        $ids = [];
        $files = glob(__DIR__.'/../../../src/Shared/Domain/Id/*.php');
        foreach (false === $files ? [] : $files as $file) {
            $class = 'Eventor\\Shared\\Domain\\Id\\'.basename($file, '.php');
            if (is_subclass_of($class, AbstractId::class)) {
                $ids[] = $class;
            }
        }

        $registered = [];
        foreach (SharedIdTypes::all() as $name => $typeClass) {
            self::assertSame($name, \constant($typeClass.'::NAME'), "$typeClass is registered under another name");
            $registered[] = (new \ReflectionMethod($typeClass, 'idClass'))->invoke(new $typeClass());
        }

        self::assertGreaterThan(30, \count($ids));
        self::assertEqualsCanonicalizing($ids, $registered, 'ids without a DBAL type, or types of unknown ids');
    }

    public function test_a_type_converts_between_the_id_and_a_uuid_string(): void
    {
        $type = new OrderIdType();
        $platform = new PostgreSQLPlatform();
        $id = OrderId::generate();

        self::assertSame($id->toString(), $type->convertToDatabaseValue($id, $platform));
        $back = $type->convertToPHPValue($id->toString(), $platform);
        self::assertInstanceOf(OrderId::class, $back);
        self::assertTrue($id->equals($back));
        self::assertNull($type->convertToPHPValue(null, $platform));
    }
}
