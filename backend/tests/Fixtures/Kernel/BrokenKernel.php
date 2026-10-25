<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Kernel;

use Eventor\Audit\Application\Query\AuditTrailQuery;
use Eventor\Identity\Application\Query\AccountProfileQuery;
use Eventor\Kernel;
use Eventor\Tests\Fixtures\Controller\UnguardedAdminController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The real kernel plus one deliberate architecture violation. Booting it is what `cache:warmup` does, and it must
 * throw: that is how the compiler passes keep the build honest (phase F0 exit criterion).
 */
final class BrokenKernel extends Kernel
{
    public const string MODULE_BOUNDARY = 'module-boundary';
    public const string EVENT_WITHOUT_SUBSCRIBER = 'event-without-subscriber';
    public const string UNGUARDED_ADMIN_CONTROLLER = 'unguarded-admin-controller';

    public function __construct(private readonly string $breakage)
    {
        parent::__construct('test', true);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/eventor-broken-kernel/'.$this->breakage;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/eventor-broken-kernel/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $breakage = $this->breakage;
        $container->addCompilerPass(new class($breakage) implements CompilerPassInterface {
            public function __construct(private readonly string $breakage) {}

            public function process(ContainerBuilder $container): void
            {
                match ($this->breakage) {
                    // The Audit module's query service is handed an internal service of Identity by configuration.
                    BrokenKernel::MODULE_BOUNDARY => $container->setDefinition('evil.audit_query', (new Definition(AuditTrailQuery::class))
                        ->setPublic(true)
                        ->setArguments([new Reference('doctrine.dbal.default_connection'), new Reference(AccountProfileQuery::class)])),
                    // A public event nobody subscribes to and nobody declared #[NoSubscribers].
                    BrokenKernel::EVENT_WITHOUT_SUBSCRIBER => $container->setParameter('eventor.integration_event_sources', [
                        __DIR__.'/../Routing/Orphan' => 'Eventor\\Tests\\Fixtures\\Routing\\Orphan',
                    ]),
                    // An admin endpoint without #[RequiresPermission].
                    BrokenKernel::UNGUARDED_ADMIN_CONTROLLER => $container->setDefinition(UnguardedAdminController::class, (new Definition(UnguardedAdminController::class))
                        ->setPublic(true)
                        ->addTag('controller.service_arguments')),
                    default => throw new \LogicException('Unknown breakage '.$this->breakage),
                };
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
