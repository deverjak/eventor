<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Tests\Integration\Architecture\ModuleCatalog;
use PHPUnit\Framework\TestCase;

/**
 * `config/module_contracts.php` is the source of the deptrac ruleset. The planned subscribers of the integration events
 * must be allowed to import the event they subscribe to, otherwise a module could not even be written.
 */
final class ModuleContractMatrixTest extends TestCase
{
    /** @var array<string, array<string, string>> */
    private array $matrix;

    protected function setUp(): void
    {
        /** @var array<string, array<string, string>> $matrix */
        $matrix = require ModuleCatalog::projectDir().'/config/module_contracts.php';
        $this->matrix = $matrix;
    }

    public function test_every_module_has_an_entry_and_only_names_real_foreign_modules(): void
    {
        $modules = array_values(array_diff(array_keys(ModuleCatalog::SCHEMAS), ['Shared']));

        self::assertEqualsCanonicalizing($modules, array_keys($this->matrix));
        foreach ($this->matrix as $module => $foreign) {
            foreach ($foreign as $other => $reason) {
                self::assertContains($other, $modules, "$module -> $other");
                self::assertNotSame($module, $other, "$module lists itself");
                self::assertNotSame('', trim($reason), "$module -> $other needs a reason");
            }
        }
    }

    public function test_every_planned_subscriber_may_import_the_events_it_subscribes_to(): void
    {
        $byLowerCase = [];
        foreach (array_keys($this->matrix) as $module) {
            $byLowerCase[strtolower($module)] = $module;
        }

        foreach (ModuleCatalog::contractEvents() as ['module' => $producer, 'class' => $class]) {
            $attributes = (new \ReflectionClass($class))->getAttributes(PendingSubscribers::class);
            foreach ([] === $attributes ? [] : $attributes[0]->newInstance()->modules as $subscriber) {
                $module = $byLowerCase[$subscriber] ?? null;
                self::assertNotNull($module, "$class lists the unknown module $subscriber");
                if ($module === $producer) {
                    continue;
                }
                self::assertArrayHasKey($producer, $this->matrix[$module], "$module subscribes to $class but may not depend on $producer.Contract (config/module_contracts.php)");
            }
        }
    }
}
