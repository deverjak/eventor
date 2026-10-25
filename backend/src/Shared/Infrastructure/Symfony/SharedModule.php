<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The shared kernel is registered like a module, so its services.php is wired exactly like the others.
 * Its compiler passes (IntegrationEventRoutingPass, ModuleBoundaryPass) are added by the Kernel, because they
 * look at every module.
 */
final class SharedModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Shared';
    }

    public function build(ContainerBuilder $container): void {}
}
