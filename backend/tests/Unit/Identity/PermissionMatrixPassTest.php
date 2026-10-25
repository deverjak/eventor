<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Infrastructure\Symfony\DependencyInjection\PermissionMatrixPass;
use Eventor\Tests\Fixtures\Controller\ClassGuardedController;
use Eventor\Tests\Fixtures\Controller\GuardedAdminController;
use Eventor\Tests\Fixtures\Controller\PrefixedUnguardedController;
use Eventor\Tests\Fixtures\Controller\PublicPingController;
use Eventor\Tests\Fixtures\Controller\UnguardedAdminController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class PermissionMatrixPassTest extends TestCase
{
    public function test_guarded_admin_actions_and_public_routes_pass(): void
    {
        $container = $this->containerWith(GuardedAdminController::class, ClassGuardedController::class, PublicPingController::class);

        (new PermissionMatrixPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function test_an_admin_action_without_a_permission_fails_the_build(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/UnguardedAdminController::__invoke\(\) serves "\/api\/admin\/org\/\{orgSlug\}\/unguarded" but has no #\[RequiresPermission\]/');

        (new PermissionMatrixPass())->process($this->containerWith(UnguardedAdminController::class));
    }

    public function test_the_admin_prefix_may_come_from_a_class_level_route(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/PrefixedUnguardedController::list\(\)/');

        (new PermissionMatrixPass())->process($this->containerWith(PrefixedUnguardedController::class));
    }

    private function containerWith(string ...$controllers): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($controllers as $class) {
            $container->setDefinition($class, (new Definition($class))->addTag('controller.service_arguments'));
        }

        return $container;
    }
}
