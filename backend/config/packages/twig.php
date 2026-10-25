<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

// Twig renders e-mails only (templates/email/*). The API never renders HTML.
return App::config([
    'twig' => ['file_name_pattern' => '*.twig'],
    'when@test' => ['twig' => ['strict_variables' => true]],
]);
