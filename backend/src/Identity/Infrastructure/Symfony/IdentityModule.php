<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Symfony;

use Eventor\Identity\Infrastructure\Symfony\DependencyInjection\PermissionMatrixPass;
use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Identity: organizers (tenants), team accounts, memberships, roles and the permission matrix.
 */
final class IdentityModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Identity';
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PermissionMatrixPass());
    }
}
