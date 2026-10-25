<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Application\Form\FieldTypeRegistry;
use Eventor\Catalog\Application\Form\Type\ShortTextType;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Eventor\Catalog\Domain\Form\FieldTypeName;
use Eventor\Catalog\Infrastructure\Symfony\DependencyInjection\FieldTypeRegistryPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Chapter 09: the container build fails when the form could accept anything. The pass runs on hand-made containers.
 */
final class FieldTypeRegistryPassTest extends TestCase
{
    public function test_a_complete_set_of_types_fills_the_registry(): void
    {
        $container = $this->container();

        (new FieldTypeRegistryPass())->process($container);

        self::assertArrayHasKey('$types', $container->getDefinition(FieldTypeRegistry::class)->getArguments());
    }

    public function test_a_field_type_name_without_implementation_fails_the_build(): void
    {
        $container = $this->container(except: FieldTypeName::Phone);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Field types without implementation: phone');
        (new FieldTypeRegistryPass())->process($container);
    }

    public function test_two_implementations_of_one_name_fail_the_build(): void
    {
        $container = $this->container();
        $container->setDefinition('duplicate', $this->type(ShortTextType::class, 'short_text'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate field type "short_text"');
        (new FieldTypeRegistryPass())->process($container);
    }

    public function test_a_type_without_one_of_the_three_capabilities_fails_the_build(): void
    {
        $container = $this->container(except: FieldTypeName::Email);
        $container->setDefinition('incomplete', $this->type(IncompleteType::class, 'email'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement');
        (new FieldTypeRegistryPass())->process($container);
    }

    public function test_a_name_that_is_not_a_field_type_name_fails_the_build(): void
    {
        $container = $this->container();
        $container->setDefinition('alien', $this->type(ShortTextType::class, 'hologram'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('not a FieldTypeName');
        (new FieldTypeRegistryPass())->process($container);
    }

    private function container(?FieldTypeName $except = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(FieldTypeRegistry::class, new Definition(FieldTypeRegistry::class));
        foreach (FieldTypeName::cases() as $name) {
            if ($name === $except) {
                continue;
            }
            $container->setDefinition('type.'.$name->value, $this->type($this->classOf($name), $name->value));
        }

        return $container;
    }

    private function type(string $class, string $name): Definition
    {
        return (new Definition($class))->addTag(AsFieldType::TAG, ['name' => $name]);
    }

    /**
     * @return class-string
     */
    private function classOf(FieldTypeName $name): string
    {
        $class = 'Eventor\\Catalog\\Application\\Form\\Type\\'.str_replace(' ', '', ucwords(str_replace('_', ' ', $name->value))).'Type';
        self::assertTrue(class_exists($class), $class.' is missing');

        return $class;
    }
}

/**
 * Has a validator but neither a normalizer nor a JSON schema.
 */
final class IncompleteType implements \Eventor\Catalog\Application\Form\ConstraintFactory
{
    public function constraints(FieldDefinition $field): array
    {
        return [];
    }
}
