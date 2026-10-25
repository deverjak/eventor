<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Shared\Application\Messaging\CommandRecordedNoDomainEvent;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Eventor\Tests\Fixtures\Command\EmitsEvent;
use Eventor\Tests\Fixtures\Command\EmitsNothing;
use Eventor\Tests\Fixtures\Command\ExemptByReason;
use Eventor\Tests\Fixtures\Command\ReadsOnly;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * ADR 001: "every command emits at least one event, or is #[ReadOnlyCommand]". The rule is enforced at runtime by
 * DomainEventsMiddleware in dev and test (so every integration test of a command checks it), and this test proves
 * the guard and checks the declarations statically.
 */
final class CommandEmitsEventTest extends IntegrationTestCase
{
    public function test_the_guard_is_switched_on_outside_production(): void
    {
        self::assertTrue(static::getContainer()->getParameter('eventor.require_domain_event'));
    }

    public function test_a_command_that_records_a_domain_event_passes(): void
    {
        $id = $this->commandBus()->dispatch(new EmitsEvent('fixture-org'));

        self::assertIsString($id);
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'organizer.registered'"));
    }

    public function test_a_command_that_records_nothing_fails_and_leaves_no_trace(): void
    {
        try {
            $this->commandBus()->dispatch(new EmitsNothing());
            self::fail('A command without a domain event must fail.');
        } catch (CommandRecordedNoDomainEvent $e) {
            self::assertStringContainsString(EmitsNothing::class, $e->getMessage());
            self::assertStringContainsString('#[ReadOnlyCommand]', $e->getMessage());
        }
    }

    public function test_read_only_commands_and_documented_exemptions_pass(): void
    {
        $this->commandBus()->dispatch(new ReadsOnly());
        $this->commandBus()->dispatch(new ExemptByReason());

        $this->addToAssertionCount(1);
    }

    public function test_every_exemption_in_the_code_base_has_a_reason(): void
    {
        $found = 0;
        foreach ($this->classesWithAttribute(NoDomainEvent::class) as $class) {
            $attribute = (new \ReflectionClass($class))->getAttributes(NoDomainEvent::class)[0]->newInstance();
            self::assertNotSame('', trim($attribute->reason), "$class: #[NoDomainEvent] needs a reason");
            ++$found;
        }

        $this->addToAssertionCount($found + 1);
    }

    public function test_command_handlers_name_their_bus_explicitly(): void
    {
        // A handler without `bus:` is registered on EVERY bus, so a command handler would also react to events.
        // Subscribers of integration events (fromTransport) are bound to one transport and exempt.
        $offenders = [];
        foreach ((new Finder())->files()->in(ModuleCatalog::projectDir().'/src')->name('*.php') as $file) {
            $class = $this->classOf($file->getPathname());
            if (null === $class) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            foreach ([$reflection, ...$reflection->getMethods()] as $reflector) {
                foreach ($reflector->getAttributes(AsMessageHandler::class) as $attribute) {
                    $handler = $attribute->newInstance();
                    if (null === $handler->bus && null === $handler->fromTransport) {
                        $offenders[] = $class;
                    }
                }
            }
        }

        self::assertSame([], $offenders, 'Add bus: "command.bus" or bus: "event.bus" to #[AsMessageHandler].');
    }

    /**
     * @return list<class-string>
     */
    private function classesWithAttribute(string $attribute): array
    {
        $classes = [];
        foreach ((new Finder())->files()->in([ModuleCatalog::projectDir().'/src', ModuleCatalog::projectDir().'/tests/Fixtures'])->name('*.php') as $file) {
            $class = $this->classOf($file->getPathname());
            if (null !== $class && [] !== (new \ReflectionClass($class))->getAttributes($attribute)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return class-string|null
     */
    private function classOf(string $path): ?string
    {
        $source = (string) file_get_contents($path);
        if (1 !== preg_match('/^namespace\s+([^;]+);/m', $source, $ns) || 1 !== preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|enum)\s+(\w+)/m', $source, $name)) {
            return null;
        }
        $class = $ns[1].'\\'.$name[1];

        return class_exists($class) || enum_exists($class) ? $class : null;
    }
}
