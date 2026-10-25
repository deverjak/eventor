<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Communication (schema `communication`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class CommunicationModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Communication';
    }

    public function build(ContainerBuilder $container): void {}
}
