<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'when@dev' => ['zenstruck_foundry' => ['persistence' => ['flush_once' => true]]],
    'when@test' => ['zenstruck_foundry' => ['persistence' => ['flush_once' => true]]],
]);
