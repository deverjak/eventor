<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

// Media and legal-document storage on MinIO (S3 API); in-memory adapter in tests.
return App::config([
    'flysystem' => [
        'storages' => [
            'media.storage' => [
                'adapter' => 'asyncaws',
                'options' => [
                    'client' => 'eventor.s3_client',
                    'bucket' => '%env(S3_BUCKET)%',
                    'prefix' => '',
                ],
            ],
        ],
    ],
    'when@test' => [
        'flysystem' => [
            'storages' => [
                'media.storage' => [
                    'adapter' => 'memory',
                ],
            ],
        ],
    ],
]);
