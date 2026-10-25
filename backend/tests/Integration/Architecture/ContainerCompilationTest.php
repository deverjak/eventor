<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Tests\Fixtures\Kernel\BrokenKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Phase F0 exit criterion: "cache:warmup fails the build on a module boundary violation (proved by a deliberately
 * broken test)". `cache:warmup` boots the kernel, and booting compiles the container; BrokenKernel is the real kernel
 * plus one deliberate violation, so these tests show that each compiler pass really stops the build.
 */
final class ContainerCompilationTest extends TestCase
{
    public function test_a_module_boundary_violation_fails_the_build(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Module boundary violation: service "evil\.audit_query" \(module Audit.*AuditTrailQuery\) depends on .*AccountProfileQuery.* \(module Identity/s');

        (new BrokenKernel(BrokenKernel::MODULE_BOUNDARY))->boot();
    }

    public function test_an_integration_event_without_subscriber_fails_the_build(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/OrphanEvent has no subscriber.*#\[NoSubscribers\]/s');

        (new BrokenKernel(BrokenKernel::EVENT_WITHOUT_SUBSCRIBER))->boot();
    }

    public function test_an_admin_endpoint_without_a_permission_fails_the_build(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/UnguardedAdminController::__invoke\(\).*no #\[RequiresPermission\]/s');

        (new BrokenKernel(BrokenKernel::UNGUARDED_ADMIN_CONTROLLER))->boot();
    }

    #[DataProvider('environments')]
    public function test_the_real_container_compiles_in_every_environment(string $environment, bool $debug): void
    {
        $kernel = new \Eventor\Kernel($environment, $debug);
        // A separate cache directory per environment keeps this test independent of the other suites.
        $kernel->boot();

        self::assertTrue($kernel->getContainer()->has('kernel'));
        $kernel->shutdown();
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function environments(): iterable
    {
        yield 'test' => ['test', true];
        yield 'dev' => ['dev', true];
        yield 'prod' => ['prod', false];
    }
}
