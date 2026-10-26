<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Content\Application\Port\PhotoStorage;
use Eventor\Content\Application\Port\Thumbnailer;
use Eventor\Content\Contract\PublicContentReader;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;
use League\Flysystem\FilesystemOperator;

// Services and framework configuration of the Content module.
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Content', \dirname(__DIR__, 2));
    // `content_events`: the outbox queue of the integration events Content subscribes to (its own photo_uploaded,
    // whose subscriber makes the thumbnail in the worker).
    ModuleServices::eventTransport($container, 'content');

    // Photos in object storage under the prefix `content/` of the S3 bucket (MinIO locally), a local directory in tests.
    $container->extension('flysystem', ['storages' => ['content.storage' => 'test' === $container->env()
        ? ['adapter' => 'local', 'options' => ['directory' => '%kernel.cache_dir%/content-storage']]
        : ['adapter' => 'asyncaws', 'options' => ['client' => 'eventor.s3_client', 'bucket' => '%env(S3_BUCKET)%', 'prefix' => 'content']],
    ]]);
    $services->alias(FilesystemOperator::class.' $content', 'content.storage');

    if ('test' === $container->env()) {
        // Public aliases so integration tests can read the stored bytes and call the facade (IntegrationTestCase::service()).
        foreach ([PhotoStorage::class, Thumbnailer::class, PublicContentReader::class] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
    }
};
