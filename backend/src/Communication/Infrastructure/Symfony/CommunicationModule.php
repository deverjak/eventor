<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Communication (schema `communication`). Services, Messenger transports, workflows and the
 * schedule live in services.php.
 */
final class CommunicationModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Communication';
    }

    public function build(ContainerBuilder $container): void {}
}
