<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Tests\Support\EventSampler;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Every integration event survives the real Messenger serializer (JSON, the transport serializer of the outbox): typed ids,
 * Money, enums, dates and lists of ids come back as the same value objects. Subscribers read the same classes the
 * publisher built, so a payload that does not round-trip would only fail in production.
 */
final class ContractEventsSerializationTest extends IntegrationTestCase
{
    public function test_every_integration_event_round_trips_through_the_transport_serializer(): void
    {
        $serializer = static::getContainer()->get('messenger.transport.symfony_serializer');
        self::assertInstanceOf(SerializerInterface::class, $serializer);

        $events = ModuleCatalog::contractEvents();
        self::assertGreaterThanOrEqual(50, \count($events));

        foreach ($events as ['class' => $class]) {
            $event = EventSampler::sample($class);

            $encoded = $serializer->encode(new Envelope($event, [new TenantStamp('0198f6a0-0000-7000-8000-000000000001')]));
            $decoded = $serializer->decode($encoded)->getMessage();

            self::assertEquals($event, $decoded, "$class does not survive serialisation");
            self::assertStringNotContainsString('sample-email', $encoded['body']);
        }
    }

    public function test_a_nullable_organizer_and_optional_fields_round_trip_as_null(): void
    {
        $serializer = static::getContainer()->get('messenger.transport.symfony_serializer');
        self::assertInstanceOf(SerializerInterface::class, $serializer);

        $event = EventSampler::sample(\Eventor\Payments\Contract\Event\IntegrationDegraded::class, ['organizerId' => null]);
        $decoded = $serializer->decode($serializer->encode(new Envelope($event)))->getMessage();

        self::assertEquals($event, $decoded);
    }
}
