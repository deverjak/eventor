<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Identity\Contract\Event\MembershipChanged;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * IntegrationEventRoutingPass in the compiled container (the pass itself is unit tested): the routing
 * event -> transports is exactly the set of subscribers, and the catalogue of events is consistent. It also guards
 * the framework-internal service the pass edits (`messenger.senders_locator`) against upgrades.
 */
final class IntegrationEventRoutingTest extends IntegrationTestCase
{
    public function test_an_event_is_routed_to_the_transport_of_every_subscriber(): void
    {
        $locator = static::getContainer()->get('messenger.senders_locator');
        self::assertInstanceOf(SendersLocatorInterface::class, $locator);

        $event = new MembershipChanged('o', 'm', 'u', 'OWNER', 'granted');
        $routed = array_keys(iterator_to_array($locator->getSenders(new Envelope($event))));

        // Test-only subscribers (tests/Fixtures/Messenger) on the Doctrine transports of the outbox proof.
        self::assertEqualsCanonicalizing(['probe_outbox', 'probe_flaky', 'probe_failing'], $routed);
    }

    public function test_an_event_without_subscribers_is_not_routed_anywhere(): void
    {
        $locator = static::getContainer()->get('messenger.senders_locator');
        self::assertInstanceOf(SendersLocatorInterface::class, $locator);

        self::assertSame([], iterator_to_array($locator->getSenders(new Envelope(new \stdClass()))));
    }

    public function test_every_contract_event_is_a_versioned_integration_event_with_a_unique_name(): void
    {
        $names = [];
        $events = ModuleCatalog::contractEvents();
        foreach ($events as ['module' => $module, 'class' => $class]) {
            $attributes = (new \ReflectionClass($class))->getAttributes(IntegrationEvent::class);

            self::assertCount(1, $attributes, "$class needs #[IntegrationEvent(name, version)]");
            $attribute = $attributes[0]->newInstance();
            self::assertGreaterThanOrEqual(1, $attribute->version);
            self::assertMatchesRegularExpression('/^'.strtolower($module).'\.[a-z_]+$/', $attribute->name, "$class: event names are '<module>.<snake_case>'");
            self::assertArrayNotHasKey($attribute->name, $names, "$class repeats the event name {$attribute->name}");
            $names[$attribute->name] = $class;
        }

        self::assertGreaterThanOrEqual(1, \count($events));
    }

    public function test_integration_events_carry_no_personal_data_by_shape(): void
    {
        $forbidden = ['email', 'phone', 'firstName', 'lastName', 'birthDate', 'name', 'address', 'iban'];
        foreach (ModuleCatalog::contractEvents() as ['class' => $class]) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getProperties() as $property) {
                self::assertNotContains($property->getName(), $forbidden, $reflection->getName().' must not carry personal data.');
            }
        }
    }
}
