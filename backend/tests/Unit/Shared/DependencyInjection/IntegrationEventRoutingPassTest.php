<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared\DependencyInjection;

use Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler\IntegrationEventRoutingPass;
use Eventor\Tests\Fixtures\Routing\Handlers\GoodSubscriber;
use Eventor\Tests\Fixtures\Routing\Happy\GoodEvent;
use Eventor\Tests\Fixtures\Routing\Orphan\OrphanEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * IntegrationEventRoutingPass on hand-made containers: it derives routing from subscribers and fails the build
 * on every inconsistency of the event catalogue (ADR 003).
 */
final class IntegrationEventRoutingPassTest extends TestCase
{
    private const array MODULES = ['Shared', 'Identity', 'Payments', 'Communication'];
    private const string FIXTURES = __DIR__.'/../../../Fixtures/Routing';
    private const string NS = 'Eventor\\Tests\\Fixtures\\Routing';

    public function test_routing_is_derived_from_the_subscribers_of_every_module(): void
    {
        $container = $this->container('Happy');
        $this->handler($container, 'payments.on_good', 'Eventor\Payments\UserInterface\EventHandler\OnGood', GoodEvent::class, 'payments_events');
        $this->handler($container, 'communication.on_good', 'Eventor\Communication\UserInterface\EventHandler\OnGood', GoodEvent::class, 'communication_events');

        $this->process($container);

        self::assertSame(
            [GoodEvent::class => ['payments_events', 'communication_events']],
            $this->routing($container),
        );
    }

    public function test_a_handler_class_is_enough_the_message_type_is_read_from_invoke(): void
    {
        $container = $this->container('Happy');
        $container->setDefinition('good.subscriber', (new Definition(GoodSubscriber::class))
            ->addTag('messenger.message_handler', ['from_transport' => 'payments_events']));

        $this->process($container);

        self::assertSame(['payments_events'], $this->routing($container)[GoodEvent::class]);
    }

    public function test_existing_configured_routing_is_kept(): void
    {
        $container = $this->container('Happy');
        $container->getDefinition('messenger.senders_locator')->replaceArgument(0, [GoodEvent::class => ['audit_events']]);
        $this->handler($container, 'payments.on_good', 'Eventor\Payments\UserInterface\EventHandler\OnGood', GoodEvent::class, 'payments_events');

        $this->process($container);

        self::assertSame(['audit_events', 'payments_events'], $this->routing($container)[GoodEvent::class]);
    }

    public function test_an_event_without_subscriber_fails_unless_it_is_marked_no_subscribers(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/OrphanEvent has no subscriber.*#\[NoSubscribers\]/s');

        $this->process($this->container('Orphan'));
    }

    public function test_two_handlers_of_one_event_on_one_transport_fail(): void
    {
        $container = $this->container('Happy');
        $this->handler($container, 'payments.first', 'Eventor\Payments\UserInterface\EventHandler\First', GoodEvent::class, 'payments_events');
        $this->handler($container, 'payments.second', 'Eventor\Payments\UserInterface\EventHandler\Second', GoodEvent::class, 'payments_events');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/two handlers of .*GoodEvent/');

        $this->process($container);
    }

    public function test_a_handler_without_from_transport_fails(): void
    {
        $container = $this->container('Happy');
        $container->setDefinition('payments.unbound', (new Definition('Eventor\Payments\UserInterface\EventHandler\Unbound'))
            ->addTag('messenger.message_handler', ['handles' => GoodEvent::class]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/has no fromTransport/');

        $this->process($container);
    }

    public function test_a_transport_that_is_not_configured_fails(): void
    {
        $container = $this->container('Happy');
        $this->handler($container, 'payments.on_good', 'Eventor\Payments\UserInterface\EventHandler\OnGood', GoodEvent::class, 'typo_events');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/transport "typo_events", which is not configured/');

        $this->process($container);
    }

    public function test_a_module_must_subscribe_on_its_own_transport(): void
    {
        $container = $this->container('Happy');
        $this->handler($container, 'payments.on_good', 'Eventor\Payments\UserInterface\EventHandler\OnGood', GoodEvent::class, 'communication_events');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/must subscribe on its own transport "payments_events"/');

        $this->process($container);
    }

    public function test_an_event_name_must_be_unique(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/"fixture\.same_name" is used by both/');

        $this->process($this->container('DuplicateName'));
    }

    public function test_a_contract_event_without_the_attribute_fails(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/ForgotAttribute.*must carry #\[IntegrationEvent/s');

        $this->process($this->container('NoAttribute/Contract/Event'));
    }

    public function test_the_orphan_fixture_is_really_an_integration_event(): void
    {
        self::assertNotEmpty((new \ReflectionClass(OrphanEvent::class))->getAttributes());
    }

    private function container(string $fixtureSet): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('eventor.integration_event_sources', [
            self::FIXTURES.'/'.$fixtureSet => self::NS.'\\'.str_replace('/', '\\', $fixtureSet),
        ]);
        $container->setDefinition('messenger.senders_locator', new Definition('stdClass', [[], null]));
        foreach (['payments_events', 'communication_events', 'identity_events'] as $transport) {
            $container->setDefinition('messenger.transport.'.$transport, new Definition('stdClass'));
        }

        return $container;
    }

    private function handler(ContainerBuilder $container, string $id, string $class, string $event, string $transport): void
    {
        $container->setDefinition($id, (new Definition($class))
            ->addTag('messenger.message_handler', ['handles' => $event, 'from_transport' => $transport]));
    }

    private function process(ContainerBuilder $container): void
    {
        (new IntegrationEventRoutingPass(sys_get_temp_dir().'/no-such-project', self::MODULES))->process($container);
    }

    /**
     * @return array<string, list<string>>
     */
    private function routing(ContainerBuilder $container): array
    {
        /** @var array<string, list<string>> $map */
        $map = $container->getDefinition('messenger.senders_locator')->getArgument(0);

        return $map;
    }
}
