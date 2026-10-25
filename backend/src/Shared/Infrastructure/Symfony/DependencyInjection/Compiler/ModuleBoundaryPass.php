<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ExceptionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Module boundaries in the DI container (chapter 02/09). deptrac sees imports, this pass sees what the container
 * really injects. It runs after unused services are removed (PassConfig::TYPE_AFTER_REMOVING) and throws, so
 * `cache:warmup` fails the build on a violation.
 *
 * A service of module A may receive a service of module B only when
 *  - B is `Shared` or the injected class lives in `B\Contract\`, or
 *  - the constructor parameter is *typed* with an interface of `B\Contract\` or `Shared` (the container has already
 *    replaced the alias by its implementation, so the declared type is what the author asked for), or
 *  - the injected class implements an interface owned by module A itself (a port of A that B implements, or a
 *    plugin registry of A such as check-in rules contributed by other modules).
 *
 * Everything else, notably handing a service of another module over in `services.php`, is a boundary violation.
 */
final readonly class ModuleBoundaryPass implements CompilerPassInterface
{
    private ModuleOf $modules;

    /**
     * @param list<string> $moduleNames
     */
    public function __construct(array $moduleNames)
    {
        $this->modules = new ModuleOf($moduleNames);
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $this->classOf($container, $definition);
            if (null === $class || null === ($from = $this->modules->name($class))) {
                continue;
            }

            foreach ($this->injections($definition, $class) as [$reference, $declaredType]) {
                $target = $this->targetClass($container, $reference);
                if (null === $target) {
                    continue;
                }

                $to = $this->modules->name($target);
                if (null === $to || $to === $from || $this->isAllowed($from, $to, $reference, $target, $declaredType)) {
                    continue;
                }

                throw new \LogicException(\sprintf(
                    'Module boundary violation: service "%s" (module %s, class %s) depends on "%s" (module %s, class %s). Use %s\\Contract instead.',
                    $id,
                    $from,
                    $class,
                    $reference,
                    $to,
                    $target,
                    'Eventor\\'.$to,
                ));
            }
        }
    }

    private function isAllowed(string $from, string $to, string $reference, string $target, ?string $declaredType): bool
    {
        if ('Shared' === $to || $this->modules->isContract($target, $to)) {
            return true;
        }
        // Requested by id (the pass also works before aliases are resolved) or by constructor type.
        foreach ([$reference, $declaredType] as $requested) {
            if (null !== $requested && $this->modules->name($requested) === $to && ('Shared' === $to || $this->modules->isContract($requested, $to))) {
                return true;
            }
        }

        $interfaces = class_exists($target) || interface_exists($target) ? class_implements($target) : false;
        foreach (false === $interfaces ? [] : $interfaces as $interface) {
            if ($this->modules->name($interface) === $from) {
                return true; // a port of the consumer, implemented elsewhere
            }
        }

        return false;
    }

    private function targetClass(ContainerBuilder $container, string $reference): ?string
    {
        try {
            return $this->classOf($container, $container->findDefinition($reference));
        } catch (ExceptionInterface) {
            return null !== $this->modules->name($reference) ? $reference : null;
        }
    }

    private function classOf(ContainerBuilder $container, Definition $definition): ?string
    {
        $class = $definition->getClass();
        if (null === $class) {
            return null;
        }

        $resolved = $container->getParameterBag()->resolveValue($class);

        return \is_string($resolved) ? $resolved : null;
    }

    /**
     * @return list<array{string, string|null}> [referenced service id, class named by the constructor parameter or null]
     */
    private function injections(Definition $definition, string $class): array
    {
        $found = [];

        $parameters = [];
        if (class_exists($class) && method_exists($class, '__construct')) {
            $parameters = (new \ReflectionMethod($class, '__construct'))->getParameters();
        }

        foreach ($definition->getArguments() as $key => $argument) {
            $declared = null;
            $parameter = \is_int($key) ? ($parameters[$key] ?? null) : $this->parameterByName($parameters, ltrim($key, '$'));
            $type = $parameter?->getType();
            if ($argument instanceof Reference && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $declared = $type->getName();
            }

            foreach ($this->collect($argument) as $reference) {
                $found[] = [$reference, $argument instanceof Reference ? $declared : null];
            }
        }

        foreach ([$definition->getProperties(), $definition->getMethodCalls(), $definition->getFactory(), $definition->getConfigurator()] as $other) {
            foreach ($this->collect($other) as $reference) {
                $found[] = [$reference, null];
            }
        }

        return $found;
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     */
    private function parameterByName(array $parameters, string $name): ?\ReflectionParameter
    {
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function collect(mixed $value): array
    {
        if ($value instanceof Reference) {
            return [(string) $value];
        }
        if ($value instanceof Definition) {
            return [...$this->collect($value->getArguments()), ...$this->collect($value->getFactory())];
        }
        if ($value instanceof ArgumentInterface) {
            return $this->collect($value->getValues());
        }
        if (\is_array($value)) {
            $found = [];
            foreach ($value as $item) {
                array_push($found, ...$this->collect($item));
            }

            return $found;
        }

        return [];
    }
}
