<?php

declare(strict_types=1);

namespace Eventor\Audit\Infrastructure\Symfony;

use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Audit: the append-only `audit.audit_entry`. It subscribes to nothing (entries are written in the transaction of
 * the change through the `AuditRecorder` port), so it declares no event transport.
 */
final class AuditModule implements ModuleInterface
{
    public function name(): string
    {
        return 'Audit';
    }

    public function build(ContainerBuilder $container): void {}
}
