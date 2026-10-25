<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Symfony;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Application\Publish\PublishRule;
use Eventor\Catalog\Infrastructure\Symfony\DependencyInjection\FieldTypeRegistryPass;
use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Catalog (schema `catalog`). `#[AsFieldType]` services are tagged by attribute
 * autoconfiguration and collected by FieldTypeRegistryPass; publish rules are tagged by their interface.
 */
final class CatalogModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            AsFieldType::class,
            static function (ChildDefinition $definition, AsFieldType $attribute): void {
                $definition->addTag(AsFieldType::TAG, ['name' => $attribute->name]);
            },
        );
        $container->registerForAutoconfiguration(PublishRule::class)->addTag(PublishRule::TAG);
        $container->addCompilerPass(new FieldTypeRegistryPass());
    }
}
