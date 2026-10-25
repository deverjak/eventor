<?php

declare(strict_types=1);

namespace Eventor\Reporting\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Reporting (schema `reporting`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class ReportingModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Reporting';
    }

    public function build(ContainerBuilder $container): void {}
}
