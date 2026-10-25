<?php

declare(strict_types=1);

namespace Eventor\Attention\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Attention: the "needs a human" queue (ADM-03). Phase F0 ships only the base: the table, the `AttentionRaiser`
 * implementation used by the dead-letter listener, and the transport for the events it will subscribe to.
 */
final class AttentionModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Attention';
    }

    public function build(ContainerBuilder $container): void {}
}
