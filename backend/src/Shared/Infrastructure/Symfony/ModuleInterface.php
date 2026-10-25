<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Modules are not bundles (chapter 09). A module contributes one class implementing this interface (module-specific
 * compiler passes, attribute autoconfiguration) and one `Infrastructure/Symfony/services.php`.
 */
interface ModuleInterface
{
    /**
     * Namespace segment of the module, e.g. "Identity" (`Eventor\Identity\…`). The Postgres schema is its lower-case form.
     */
    public function name(): string;

    public function build(ContainerBuilder $container): void;
}
