<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Catalog (schema `catalog`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class CatalogModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function build(ContainerBuilder $container): void {}
}
