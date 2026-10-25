<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Payments (schema `payments`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class PaymentsModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Payments';
    }

    public function build(ContainerBuilder $container): void {}
}
