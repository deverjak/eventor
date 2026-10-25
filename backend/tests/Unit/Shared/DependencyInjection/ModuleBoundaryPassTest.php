<?php

declare(strict_types=1);

// Fictional modules Alpha and Beta, declared here so the test needs no real module (and cannot clash with one).

namespace Eventor\Alpha\Contract {
    interface Greeter {}
}

namespace Eventor\Alpha\Application {
    use Eventor\Alpha\Contract\Greeter;

    final class GreeterImpl implements Greeter {}

    final class InternalHelper {}

    /** A port owned by Alpha, implemented by another module. */
    interface Clock {}
}

namespace Eventor\Beta\Application {
    use Eventor\Alpha\Application\Clock;
    use Eventor\Alpha\Application\InternalHelper;
    use Eventor\Alpha\Contract\Greeter;

    /** Asks for the public contract: fine, even though the alias resolves to Alpha's implementation. */
    final class WellBehaved
    {
        public function __construct(public Greeter $greeter) {}
    }

    /** Asks for an internal class of Alpha. */
    final class Sneaky
    {
        public function __construct(public InternalHelper $helper) {}
    }

    final class ClockAdapter implements Clock {}
}

namespace Eventor\Alpha\UserInterface {
    use Eventor\Alpha\Application\Clock;

    /** Alpha defines the port `Clock`; Beta's adapter implements it. */
    final class UsesItsOwnPort
    {
        public function __construct(public Clock $clock) {}
    }
}

namespace Eventor\Tests\Unit\Shared\DependencyInjection {
    use Eventor\Alpha\Application\GreeterImpl;
    use Eventor\Alpha\Application\InternalHelper;
    use Eventor\Alpha\Contract\Greeter;
    use Eventor\Alpha\UserInterface\UsesItsOwnPort;
    use Eventor\Beta\Application\ClockAdapter;
    use Eventor\Beta\Application\Sneaky;
    use Eventor\Beta\Application\WellBehaved;
    use Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler\ModuleBoundaryPass;
    use PHPUnit\Framework\TestCase;
    use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
    use Symfony\Component\DependencyInjection\ContainerBuilder;
    use Symfony\Component\DependencyInjection\Definition;
    use Symfony\Component\DependencyInjection\Reference;

    /**
     * "cache:warmup fails on a module boundary violation": the pass runs in Kernel::build() as TYPE_AFTER_REMOVING;
     * here it is exercised on hand-made container definitions as they look after alias resolution (the kernel-level
     * proof is ModuleBoundaryViolationTest).
     */
    final class ModuleBoundaryPassTest extends TestCase
    {
        private const array MODULES = ['Shared', 'Alpha', 'Beta'];

        public function test_a_contract_interface_is_fine_even_though_the_alias_is_already_resolved_to_the_implementation(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition(GreeterImpl::class, new Definition(GreeterImpl::class));
            // after ResolveReferencesToAliasesPass the argument references the implementation id
            $container->setDefinition(WellBehaved::class, (new Definition(WellBehaved::class))->setArguments([new Reference(GreeterImpl::class)]));

            $this->process($container);

            $this->addToAssertionCount(1);
        }

        public function test_a_contract_id_is_fine_before_alias_resolution_too(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition(GreeterImpl::class, new Definition(GreeterImpl::class));
            $container->setAlias(Greeter::class, GreeterImpl::class);
            $container->setDefinition('consumer', (new Definition('Eventor\Beta\Application\NoSuchClass'))->setArguments([new Reference(Greeter::class)]));

            $this->process($container);

            $this->addToAssertionCount(1);
        }

        public function test_an_internal_service_of_another_module_fails_the_build(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition(InternalHelper::class, new Definition(InternalHelper::class));
            $container->setDefinition(Sneaky::class, (new Definition(Sneaky::class))->setArguments([new Reference(InternalHelper::class)]));

            $this->expectException(\LogicException::class);
            $this->expectExceptionMessageMatches('/Module boundary violation.*Sneaky.*Beta.*InternalHelper.*Alpha.*Eventor\\\\Alpha\\\\Contract/s');

            $this->process($container);
        }

        public function test_the_boundary_cannot_be_bypassed_with_an_iterator_or_locator(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition(InternalHelper::class, new Definition(InternalHelper::class));
            $container->setDefinition('beta.registry', (new Definition(Sneaky::class))->setArguments([
                new IteratorArgument(['k' => new Reference(InternalHelper::class)]),
            ]));

            $this->expectException(\LogicException::class);

            $this->process($container);
        }

        public function test_a_module_may_depend_on_shared_and_on_itself(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition('Eventor\Shared\Application\Audit\AuditRecorder', new Definition('Eventor\Shared\Application\Audit\AuditRecorder'));
            $container->setDefinition(InternalHelper::class, new Definition(InternalHelper::class));
            $container->setDefinition('alpha.user', (new Definition('Eventor\Alpha\Application\SomeUser'))->setArguments([
                new Reference('Eventor\Shared\Application\Audit\AuditRecorder'),
                new Reference(InternalHelper::class),
            ]));

            $this->process($container);

            $this->addToAssertionCount(1);
        }

        public function test_a_port_owned_by_the_consumer_may_be_implemented_by_another_module(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition(ClockAdapter::class, new Definition(ClockAdapter::class));
            $container->setDefinition(UsesItsOwnPort::class, (new Definition(UsesItsOwnPort::class))->setArguments([new Reference(ClockAdapter::class)]));

            $this->process($container);

            $this->addToAssertionCount(1);
        }

        public function test_vendor_and_test_classes_are_not_modules(): void
        {
            $container = new ContainerBuilder();
            $container->setDefinition(InternalHelper::class, new Definition(InternalHelper::class));
            $container->setDefinition('vendor.thing', (new Definition('Vendor\Thing'))->setArguments([new Reference(InternalHelper::class)]));
            $container->setDefinition('Eventor\Tests\Fixtures\Probe', (new Definition('Eventor\Tests\Fixtures\Probe'))->setArguments([new Reference(InternalHelper::class)]));

            $this->process($container);

            $this->addToAssertionCount(1);
        }

        private function process(ContainerBuilder $container): void
        {
            (new ModuleBoundaryPass(self::MODULES))->process($container);
        }
    }
}
