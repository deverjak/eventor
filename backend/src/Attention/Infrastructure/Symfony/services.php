<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

return static function (ContainerConfigurator $container): void {
    ModuleServices::register($container, 'Attention', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'attention');
};
