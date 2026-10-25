<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

// Controllers are services tagged by #[AsController]; their #[Route] attributes are collected here.
return Routes::config([
    'controllers' => [
        'resource' => 'routing.controllers',
    ],
]);
