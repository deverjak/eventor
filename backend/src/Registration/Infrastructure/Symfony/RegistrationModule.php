<?php

declare(strict_types=1);

namespace Eventor\Registration\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Registration (schema `registration`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class RegistrationModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Registration';
    }

    public function build(ContainerBuilder $container): void {}
}
