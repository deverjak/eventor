<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Catalog;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Base of the Catalog HTTP integration tests: an organizer "klub" with its owner and helpers that build the demo event
 * "Borský podzimní víkend 2026" through the admin API.
 */
abstract class CatalogHttpTestCase extends HttpTestCase
{
    use Factories;

    protected const string OWNER = 'jana@example.test';
    protected const string BASE = '/api/admin/org/klub';

    /** @var array{organizerId: string, userAccountId: string, membershipId: string} */
    protected array $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createOwner(self::OWNER, 'klub', 'Klub Plzeň');
    }

    /**
     * Adds a member of the given role to the organizer "klub" (the account is created with an organizer of its own).
     */
    protected function addMember(string $email, Role $role): void
    {
        $account = $this->createOwner($email, 'own-'.explode('@', $email)[0]);
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($account['userAccountId']),
            'organizerId' => OrganizerId::fromString($this->owner['organizerId']),
            'role' => $role,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    protected static function details(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Borský podzimní víkend 2026',
            'shortDescription' => 'Závody v Borském parku',
            'description' => 'Sobota a neděle v Plzni.',
            'startsAt' => '2026-11-21T08:00:00+01:00',
            'endsAt' => '2026-11-22T16:00:00+01:00',
            'venues' => [['name' => 'Borský park', 'address' => 'Plzeň', 'mapUrl' => 'https://mapy.cz/borsky-park']],
        ];
    }

    /**
     * @param array<string, mixed> $detailsOverrides
     *
     * @return array<string, mixed> the created ids
     */
    protected function createEvent(string $mode = 'multi', array $detailsOverrides = [], ?string $slug = null): array
    {
        $body = ['details' => self::details($detailsOverrides), 'mode' => $mode];
        if (null !== $slug) {
            $body['slug'] = $slug;
        }
        $created = $this->request('POST', self::BASE.'/events', $body);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertIsArray($created);

        return $created;
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function addActivity(string $eventId, string $name, array $extra = []): string
    {
        $body = $this->request('POST', self::BASE.'/events/'.$eventId.'/activities', ['name' => $name] + $extra);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertIsArray($body);
        self::assertIsString($body['activityId']);

        return $body['activityId'];
    }

    protected function addVariant(string $eventId, string $activityId, string $name, int $priceMinor, ?int $capacity = null, ?int $teamSize = null): string
    {
        $body = $this->request('POST', self::BASE.'/events/'.$eventId.'/activities/'.$activityId.'/variants', [
            'name' => $name,
            'price' => ['minor' => $priceMinor, 'currency' => 'CZK'],
            'capacityLimit' => $capacity,
            'teamSize' => $teamSize,
        ]);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        self::assertIsArray($body);
        self::assertIsString($body['variantId']);

        return $body['variantId'];
    }

    /**
     * A publishable event: two activities with variants, cash payment, the default form.
     *
     * @return array{eventId: string, slug: string, trail: string, relay: string, trailVariant: string, relayVariant: string}
     */
    protected function createPublishableEvent(?string $slug = null): array
    {
        $created = $this->createEvent(slug: $slug);
        $eventId = (string) $created['eventId'];
        $trail = $this->addActivity($eventId, 'Trail 5 km', ['startsAt' => '2026-11-21T10:00:00+01:00', 'capacityLimit' => 100]);
        $relay = $this->addActivity($eventId, 'Týmová štafeta', ['startsAt' => '2026-11-21T14:00:00+01:00']);
        $trailVariant = $this->addVariant($eventId, $trail, 'Jednotlivec', 30000, 100);
        $relayVariant = $this->addVariant($eventId, $relay, 'Tým 4 os.', 120000, 20, 4);
        $this->request('PUT', self::BASE.'/events/'.$eventId.'/payment-rules', ['methods' => ['cash'], 'dueDays' => 7, 'holdTtlHours' => 48]);
        self::assertResponseIsSuccessful();

        return ['eventId' => $eventId, 'slug' => (string) $created['slug'], 'trail' => $trail, 'relay' => $relay, 'trailVariant' => $trailVariant, 'relayVariant' => $relayVariant];
    }

    protected function publish(string $eventId): void
    {
        $this->request('POST', self::BASE.'/events/'.$eventId.'/publish');
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
    }
}
