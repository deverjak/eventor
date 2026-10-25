<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use AsyncAws\S3\S3Client;
use Eventor\Shared\Infrastructure\Doctrine\SharedIdTypes;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;
use Eventor\Shared\Infrastructure\Symfony\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Shared', \dirname(__DIR__, 2));

    // Typed ids that cross module boundaries (Shared\Domain\Id\*): every module may map them, none registers them.
    ModuleServices::dbalTypes($container, SharedIdTypes::all());

    // Team sessions in Postgres (ADR 006). Test env uses the mock file storage and never instantiates this.
    $services->set(PdoSessionHandler::class)
        ->factory([SessionHandlerFactory::class, 'create'])
        ->args(['%env(DATABASE_URL)%']);

    // S3 API of MinIO (dev) or any S3-compatible store; consumed by the Flysystem storage `media.storage`.
    $services->set('eventor.s3_client', S3Client::class)
        ->args([[
            'endpoint' => '%env(S3_ENDPOINT)%',
            'accessKeyId' => '%env(S3_KEY)%',
            'accessKeySecret' => '%env(S3_SECRET)%',
            'region' => '%env(S3_REGION)%',
            'pathStyleEndpoint' => true,
        ]]);

    // The CSRF guard listens only on the cookie-authenticated zones (see CsrfGuardListener).
};
