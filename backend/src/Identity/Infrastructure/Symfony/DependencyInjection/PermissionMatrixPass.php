<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Symfony\DependencyInjection;

use Eventor\Identity\Application\Access\PermissionMatrix;
use Eventor\Identity\Contract\Attribute\RequiresPermission;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\Membership\Role;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Keeps the permission matrix and the controllers honest at `cache:warmup` (chapter 09):
 *  - every `Permission` is granted to at least one role, and the matrix names only existing roles/permissions,
 *  - every controller action routed under `/api/admin` declares `#[RequiresPermission]`, so a new admin
 *    endpoint cannot ship without an authorization decision.
 */
final class PermissionMatrixPass implements CompilerPassInterface
{
    private const string ADMIN_PREFIX = '/api/admin';

    public function process(ContainerBuilder $container): void
    {
        $this->checkMatrix();

        foreach ($container->findTaggedServiceIds('controller.service_arguments') as $id => $tags) {
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());
            if (!\is_string($class) || !class_exists($class)) {
                continue;
            }
            $this->checkController(new \ReflectionClass($class));
        }
    }

    private function checkMatrix(): void
    {
        foreach (array_keys(PermissionMatrix::MATRIX) as $role) {
            if (null === Role::tryFrom($role)) {
                throw new \LogicException(\sprintf('PermissionMatrix names the unknown role "%s".', $role));
            }
        }
        foreach (PermissionMatrix::CONDITIONAL as $role => $cells) {
            if (null === Role::tryFrom($role)) {
                throw new \LogicException(\sprintf('PermissionMatrix::CONDITIONAL names the unknown role "%s".', $role));
            }
            foreach (array_keys($cells) as $permission) {
                if (null === Permission::tryFrom($permission)) {
                    throw new \LogicException(\sprintf('PermissionMatrix::CONDITIONAL names the unknown permission "%s".', $permission));
                }
            }
        }

        $covered = array_map(static fn(Permission $permission): string => $permission->value, PermissionMatrix::coveredPermissions());
        foreach (Permission::cases() as $permission) {
            if (!\in_array($permission->value, $covered, true)) {
                throw new \LogicException(\sprintf('Permission "%s" is not granted to any role in PermissionMatrix.', $permission->value));
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    private function checkController(\ReflectionClass $class): void
    {
        $classPrefix = '';
        foreach ($class->getAttributes(Route::class) as $attribute) {
            $classPrefix = $this->pathOf($attribute->newInstance());
        }
        $classGuarded = [] !== $class->getAttributes(RequiresPermission::class);

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Route::class) as $attribute) {
                $path = $classPrefix.$this->pathOf($attribute->newInstance());
                if (!str_starts_with($path, self::ADMIN_PREFIX.'/') && self::ADMIN_PREFIX !== $path) {
                    continue;
                }

                if (!$classGuarded && [] === $method->getAttributes(RequiresPermission::class)) {
                    throw new \LogicException(\sprintf(
                        '%s::%s() serves "%s" but has no #[RequiresPermission]. Every admin action needs a permission from the matrix.',
                        $class->getName(),
                        $method->getName(),
                        $path,
                    ));
                }
            }
        }
    }

    private function pathOf(Route $route): string
    {
        $path = $route->path;

        return \is_string($path) ? $path : (string) (array_values($path ?? [])[0] ?? '');
    }
}
