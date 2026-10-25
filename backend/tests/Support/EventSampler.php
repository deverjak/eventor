<?php

declare(strict_types=1);

namespace Eventor\Tests\Support;

use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\AbstractId;
use Eventor\Shared\Domain\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Builds a valid instance of any contract class from its constructor signature: ids are generated, enums take their
 * first case, lists get one element (read from the `@param list<X>` docblock). Used to prove properties of ALL
 * integration events at once (serialisation round trip, no personal data) without a hand-written fixture per event.
 */
final class EventSampler
{
    public const string NOW = '2026-11-01T12:00:00+00:00';

    private const string ID_NAMESPACE = 'Eventor\\Shared\\Domain\\Id\\';

    /**
     * @param class-string         $class
     * @param array<string, mixed> $override constructor arguments by name
     */
    public static function sample(string $class, array $override = []): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $doc = (string) $constructor?->getDocComment();
        $arguments = [];
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $arguments[$name] = \array_key_exists($name, $override) ? $override[$name] : self::value($parameter, $doc);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private static function value(\ReflectionParameter $parameter, string $doc): mixed
    {
        $type = $parameter->getType();
        \assert($type instanceof \ReflectionNamedType);

        if ('array' === $type->getName()) {
            if (1 === preg_match('/@param\s+(?:non-empty-)?list<([^>]+)>\s+\$'.$parameter->getName().'\b/', $doc, $m)) {
                return [self::scalarOf(trim($m[1]), $parameter->getName())];
            }

            return [];
        }

        return self::scalarOf($type->getName(), $parameter->getName());
    }

    private static function scalarOf(string $type, string $parameterName): mixed
    {
        return match ($type) {
            'string' => 'sample-'.$parameterName,
            'int' => 3,
            'bool' => true,
            default => self::object(class_exists($type) || enum_exists($type) ? $type : self::ID_NAMESPACE.$type),
        };
    }

    private static function object(string $class): mixed
    {
        if (\DateTimeImmutable::class === $class) {
            return new \DateTimeImmutable(self::NOW);
        }
        if (Money::class === $class) {
            return Money::of(30_000, Currency::CZK);
        }
        if (Uuid::class === $class) {
            return Uuid::v7();
        }
        if (is_subclass_of($class, AbstractId::class)) {
            return $class::generate();
        }
        if (is_subclass_of($class, \BackedEnum::class)) {
            return $class::cases()[0];
        }

        throw new \LogicException("EventSampler cannot build $class");
    }
}
