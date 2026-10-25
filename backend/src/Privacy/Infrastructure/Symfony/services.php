<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the Privacy module. Add the module's own configuration below
// (Messenger transport, workflows, Doctrine DBAL types, ...) so that shared config files stay untouched.
return static function (ContainerConfigurator $container): void {
    ModuleServices::register($container, 'Privacy', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'privacy');
};
