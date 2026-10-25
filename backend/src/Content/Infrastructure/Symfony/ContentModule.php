<?php

declare(strict_types=1);

namespace Eventor\Content\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Module registration for Content (schema `content`). Compiler passes and attribute autoconfiguration of the
 * module are registered in build(); services, Messenger transport, workflows and schedules live in services.php.
 */
final class ContentModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Content';
    }

    public function build(ContainerBuilder $container): void {}
}
