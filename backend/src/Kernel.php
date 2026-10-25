<?php

declare(strict_types=1);

namespace Eventor;

use Eventor\Attention\Infrastructure\Symfony\AttentionModule;
use Eventor\Audit\Infrastructure\Symfony\AuditModule;
use Eventor\Catalog\Infrastructure\Symfony\CatalogModule;
use Eventor\CheckIn\Infrastructure\Symfony\CheckInModule;
use Eventor\Communication\Infrastructure\Symfony\CommunicationModule;
use Eventor\Content\Infrastructure\Symfony\ContentModule;
use Eventor\Identity\Infrastructure\Symfony\IdentityModule;
use Eventor\Legal\Infrastructure\Symfony\LegalModule;
use Eventor\Payments\Infrastructure\Symfony\PaymentsModule;
use Eventor\Privacy\Infrastructure\Symfony\PrivacyModule;
use Eventor\Registration\Infrastructure\Symfony\RegistrationModule;
use Eventor\Reporting\Infrastructure\Symfony\ReportingModule;
use Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler\IntegrationEventRoutingPass;
use Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler\ModuleBoundaryPass;
use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Eventor\Shared\Infrastructure\Symfony\SharedModule;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Modules are not bundles (chapter 09): each module contributes one `<Module>Module` class (compiler passes,
 * attribute autoconfiguration) and one `src/<Module>/Infrastructure/Symfony/services.php` imported below.
 *
 * Not final on purpose: tests subclass it to compile a deliberately broken container (compiler passes must fail the build).
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<ModuleInterface>
     */
    public function modules(): iterable
    {
        yield new SharedModule();
        yield new IdentityModule();
        yield new CatalogModule();
        yield new LegalModule();
        yield new RegistrationModule();
        yield new PaymentsModule();
        yield new CheckInModule();
        yield new CommunicationModule();
        yield new ContentModule();
        yield new PrivacyModule();
        yield new AuditModule();
        yield new AttentionModule();
        yield new ReportingModule();
    }

    /**
     * Parallel agents run the test suite with different TEST_TOKENs in one container: give each its own
     * compiled container so they never race on `var/cache/test`.
     */
    public function getCacheDir(): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? null;
        if ('test' === $this->environment && \is_string($token) && '' !== $token && !isset($_SERVER['APP_CACHE_DIR'])) {
            return $this->getProjectDir().'/var/cache/test_'.preg_replace('/\W/', '', $token);
        }

        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    protected function build(ContainerBuilder $container): void
    {
        $names = [];
        foreach ($this->modules() as $module) {
            $names[] = $module->name();
            $module->build($container);
        }

        $container->addCompilerPass(new IntegrationEventRoutingPass($this->getProjectDir(), $names));
        $container->addCompilerPass(new ModuleBoundaryPass($names), PassConfig::TYPE_AFTER_REMOVING);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $config = $this->getProjectDir().'/config';

        // Braces make the loader treat these as globs, so a missing optional file or directory is not an error.
        $container->import($config.'/{packages}/*.php');
        $container->import($config.'/{packages}/'.$this->environment.'/*.php');
        $container->import($config.'/services.php');
        $container->import($config.'/{services}_'.$this->environment.'.php');
        // One services file per module; modules also declare their own Messenger transport / workflows /
        // schedules / Doctrine mapping here via $container->extension('framework', …).
        $container->import($this->getProjectDir().'/src/*/Infrastructure/Symfony/services.php');
    }

    /**
     * @return list<string>
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
