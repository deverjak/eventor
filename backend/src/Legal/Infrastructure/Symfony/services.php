<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Legal\Application\Port\DocumentContentStore;
use Eventor\Legal\Application\Port\ExternalDocumentFetcher;
use Eventor\Legal\Contract\ConfirmationRecorder;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;
use Eventor\Tests\Integration\Legal\Support\FakeExternalDocumentFetcher;
use League\Flysystem\FilesystemOperator;

// Services and framework configuration of the Legal module. Add the module's own configuration below
// (Messenger transport, workflows, Doctrine DBAL types, ...) so that shared config files stay untouched.
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Legal', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'legal');

    // The `legal` storage target (ADR 012, chapter 07): immutable, content-addressed document versions under the
    // prefix `legal/` of the S3 bucket (MinIO locally); in-memory in tests. Injected with #[Target('legal')].
    $container->extension('flysystem', ['storages' => ['legal.storage' => 'test' === $container->env()
        ? ['adapter' => 'local', 'options' => ['directory' => '%kernel.cache_dir%/legal-storage']]
        : [
            'adapter' => 'asyncaws',
            'options' => ['client' => 'eventor.s3_client', 'bucket' => '%env(S3_BUCKET)%', 'prefix' => 'legal'],
        ],
    ]]);
    $services->alias(FilesystemOperator::class.' $legal', 'legal.storage');

    if ('test' === $container->env()) {
        // External links are never fetched from the network in tests.
        $services->set(FakeExternalDocumentFetcher::class);
        // Spies that observe events whose real subscribers (other modules) are written elsewhere.
        $services->load('Eventor\\Tests\\Integration\\Legal\\Support\\', \dirname(__DIR__, 4).'/tests/Integration/Legal/Support/')
            ->exclude(\dirname(__DIR__, 4).'/tests/Integration/Legal/Support/FakeExternalDocumentFetcher.php');
        $services->alias(ExternalDocumentFetcher::class, FakeExternalDocumentFetcher::class);

        // Public aliases so integration tests can call the facades and read the stored bytes (IntegrationTestCase::service()).
        foreach ([ConfirmationRecorder::class, ConsentQuery::class, RequirementResolver::class, DocumentContentStore::class] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
    }
};
