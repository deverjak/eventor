<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;
use Eventor\Shared\Domain\Id\AbstractId;

/**
 * Doctrine mapping of a typed id: a native Postgres `uuid` column that hydrates to the id class.
 *
 *     final class OrderIdType extends AbstractIdType
 *     {
 *         public const string NAME = 'order_id';
 *         protected function idClass(): string { return OrderId::class; }
 *     }
 *
 * Register it with ModuleServices::dbalTypes($container, [OrderIdType::NAME => OrderIdType::class]) in the module's
 * services.php and use `#[ORM\Column(type: OrderIdType::NAME)]`.
 */
abstract class AbstractIdType extends GuidType
{
    /**
     * @return class-string<AbstractId>
     */
    abstract protected function idClass(): string;

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AbstractId
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $class = $this->idClass();
        if ($value instanceof $class) {
            return $value;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, static::class, ['null', 'string', $class]);
        }

        try {
            return $class::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ValueNotConvertible::new($value, static::class, null, $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $class = $this->idClass();
        if ($value instanceof $class) {
            return $value->toString();
        }
        if (\is_string($value) && $class::isValid($value)) {
            return $class::fromString($value)->toString();
        }

        throw InvalidType::new($value, static::class, ['null', 'string', $class]);
    }
}
