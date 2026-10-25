<?php

declare(strict_types=1);

namespace Eventor\Privacy\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Privacy (schema `privacy`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class PrivacyModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Privacy';
    }

    public function build(ContainerBuilder $container): void {}
}
