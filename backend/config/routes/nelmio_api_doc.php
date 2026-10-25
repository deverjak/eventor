<?php

declare(strict_types=1);

namespace Symfony\Component\Routing\Loader\Configurator;

// OpenAPI 3 document generated from route attributes: GET /api/doc.json
return Routes::config([
    'app.swagger' => [
        'path' => '/api/doc.json',
        'controller' => 'nelmio_api_doc.controller.swagger',
        'methods' => ['GET'],
    ],
]);
