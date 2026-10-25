<?php

declare(strict_types=1);

namespace Eventor\Legal\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Legal (schema `legal`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class LegalModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Legal';
    }

    public function build(ContainerBuilder $container): void {}
}
