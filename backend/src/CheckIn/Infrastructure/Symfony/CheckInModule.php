<?php

declare(strict_types=1);

namespace Eventor\CheckIn\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for CheckIn (schema `checkin`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class CheckInModule implements ModuleInterface
{
    public function name(): string
    {
        return 'CheckIn';
    }

    public function build(ContainerBuilder $container): void {}
}
