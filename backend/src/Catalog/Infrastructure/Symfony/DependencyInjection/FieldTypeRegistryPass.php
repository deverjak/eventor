<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Symfony\DependencyInjection;

use Eventor\Catalog\Application\Form\AnswerNormalizer;
use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Application\Form\ConstraintFactory;
use Eventor\Catalog\Application\Form\FieldTypeRegistry;
use Eventor\Catalog\Application\Form\JsonSchemaFragment;
use Eventor\Catalog\Domain\Form\FieldTypeName;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Collects the `#[AsFieldType]` services into the registry and fails the container build when the form could accept
 * anything (chapter 09, ADR 007): a type without one of the three capabilities, two types with one name, a name that
 * is not a `FieldTypeName`, or a `FieldTypeName` case without implementation.
 */
final class FieldTypeRegistryPass implements CompilerPassInterface
{
    private const array REQUIRED = [ConstraintFactory::class, AnswerNormalizer::class, JsonSchemaFragment::class];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(FieldTypeRegistry::class)) {
            return;
        }

        $map = [];
        foreach ($container->findTaggedServiceIds(AsFieldType::TAG) as $id => $tags) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());
            if (!\is_string($class) || !class_exists($class)) {
                throw new \LogicException(\sprintf('Field type service "%s" has no class.', $id));
            }
            foreach (self::REQUIRED as $interface) {
                if (!is_subclass_of($class, $interface)) {
                    throw new \LogicException(\sprintf('Field type "%s" must implement %s.', $class, $interface));
                }
            }
            $first = $tags[0] ?? [];
            $name = \is_array($first) ? ($first['name'] ?? null) : null;
            if (!\is_string($name) || null === FieldTypeName::tryFrom($name)) {
                throw new \LogicException(\sprintf('Field type "%s" is registered as "%s", which is not a FieldTypeName.', $class, \is_string($name) ? $name : 'null'));
            }
            if (isset($map[$name])) {
                throw new \LogicException(\sprintf('Duplicate field type "%s".', $name));
            }
            $map[$name] = new Reference($id);
        }

        $missing = array_diff(array_map(static fn(FieldTypeName $name): string => $name->value, FieldTypeName::cases()), array_keys($map));
        if ([] !== $missing) {
            throw new \LogicException('Field types without implementation: '.implode(', ', $missing));
        }

        $container->getDefinition(FieldTypeRegistry::class)->setArgument('$types', ServiceLocatorTagPass::register($container, $map));
    }
}
