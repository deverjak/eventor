<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler;

/**
 * Maps a class name to the module it belongs to: `Eventor\Registration\Application\X` -> "Registration".
 * Vendor classes, the Kernel and test code (`Eventor\Tests\…`) belong to no module.
 */
final readonly class ModuleOf
{
    /**
     * @param list<string> $modules known module names (including "Shared")
     */
    public function __construct(private array $modules) {}

    public function name(string $class): ?string
    {
        if (1 !== preg_match('/^Eventor\\\\([A-Za-z0-9]+)\\\\/', ltrim($class, '\\'), $m)) {
            return null;
        }

        return \in_array($m[1], $this->modules, true) ? $m[1] : null;
    }

    public function isContract(string $class, string $module): bool
    {
        return str_starts_with(ltrim($class, '\\'), \sprintf('Eventor\\%s\\Contract\\', $module));
    }
}
