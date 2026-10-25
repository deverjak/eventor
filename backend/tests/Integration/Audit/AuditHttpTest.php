<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Audit;

use Eventor\Audit\Contract\AuditActorKind;
use Eventor\Audit\Contract\AuditTrail;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Application\Audit\AuditRecord;
use Eventor\Shared\Application\Audit\AuditRecorder;
use Eventor\Shared\Domain\Audit\ActorType;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * AUD-01..04 through the admin API and the Contract: history of an entity, filter by actor kind, organizer-wide
 * log for owners, access by role.
 */
final class AuditHttpTest extends HttpTestCase
{
    use Factories;

    private string $organizerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizerId = $this->createOwner('jana@example.test', 'klub')['organizerId'];
    }

    public function test_the_history_of_an_entity_is_a_page_newest_first(): void
    {
        $this->login('jana@example.test');
        $this->record('event', 'ev-1', 'event.created', ActorType::User, '2026-11-01 10:00:00+00');
        $this->record('event', 'ev-1', 'event.details_changed', ActorType::System, '2026-11-01 11:00:00+00');
        $this->record('event', 'ev-2', 'event.created', ActorType::User, '2026-11-01 12:00:00+00');

        $body = $this->auditPage('GET', '/api/admin/org/klub/audit?entityType=event&entityId=ev-1');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, $body['total']);
        self::assertSame(['event.details_changed', 'event.created'], array_column($body['items'], 'action'));
        $first = $body['items'][0];
        self::assertSame('event', $first['entityType']);
        self::assertSame('ev-1', $first['entityId']);
        self::assertSame('SYSTEM', $first['actor']['type']);
        self::assertArrayHasKey('correlationId', $first);
        self::assertSame(1, $body['page']);
    }

    public function test_the_history_can_be_filtered_by_actor_kind_and_paged(): void
    {
        $this->login('jana@example.test');
        $kinds = [ActorType::User, ActorType::System, ActorType::ParticipantToken, ActorType::Device, ActorType::User];
        foreach ($kinds as $i => $kind) {
            $this->record('participation', 'p-1', 'participation.step_'.$i, $kind, \sprintf('2026-11-01 1%d:00:00+00', $i));
        }

        foreach (['user' => 2, 'system' => 1, 'participant' => 1, 'device' => 1, 'pairrot' => 0] as $actor => $expected) {
            $body = $this->auditPage('GET', "/api/admin/org/klub/audit?entityType=participation&entityId=p-1&actor=$actor");
            self::assertSame($expected, $body['total'], $actor);
        }

        $page = $this->auditPage('GET', '/api/admin/org/klub/audit?entityType=participation&entityId=p-1&perPage=2&page=3');
        self::assertCount(1, $page['items']);
        self::assertSame(5, $page['total']);
        self::assertSame(['participation.step_0'], array_column($page['items'], 'action'));
    }

    public function test_the_contract_filters_by_actor_kind(): void
    {
        $organizer = OrganizerId::fromString($this->organizerId);
        $this->record('order', 'o-1', 'order.placed', ActorType::ParticipantToken, '2026-11-01 10:00:00+00');
        $this->record('order', 'o-1', 'order.cancelled', ActorType::User, '2026-11-01 11:00:00+00');
        $trail = $this->service(AuditTrail::class);

        self::assertCount(2, $trail->forEntity($organizer, 'order', 'o-1'));
        self::assertSame(['order.placed'], array_map(static fn($e) => $e->action, $trail->forEntity($organizer, 'order', 'o-1', AuditActorKind::Participant)));
        self::assertSame([], $trail->forEntity($organizer, 'order', 'o-1', AuditActorKind::Device));
    }

    public function test_the_organizer_trail_never_shows_another_organizers_entries(): void
    {
        $other = $this->createOwner('petr@example.test', 'cizi')['organizerId'];
        $this->record('event', 'shared-id', 'event.created', ActorType::User, '2026-11-01 10:00:00+00', $other);
        $this->login('jana@example.test');

        $body = $this->auditPage('GET', '/api/admin/org/klub/audit?entityType=event&entityId=shared-id');

        self::assertSame(0, $body['total']);
        $log = $this->auditPage('GET', '/api/admin/org/klub/audit-log?entityType=event');
        self::assertSame(0, $log['total']);
    }

    public function test_missing_or_invalid_parameters_are_422(): void
    {
        $this->login('jana@example.test');

        foreach (['', '?entityType=event', '?entityId=x', '?entityType=event&entityId=x&actor=robot', '?entityType=event&entityId=x&page=0', '?entityType=event&entityId=x&perPage=500'] as $query) {
            $this->request('GET', '/api/admin/org/klub/audit'.$query);
            $this->assertProblem(422, 'validation_failed');
        }
    }

    public function test_the_owner_reads_the_organizer_wide_log(): void
    {
        $this->login('jana@example.test'); // createOwner left organizer.registered + membership.granted entries
        $this->record('event', 'ev-1', 'event.created', ActorType::Device, '2026-12-01 10:00:00+00');

        $body = $this->auditPage('GET', '/api/admin/org/klub/audit-log');
        self::assertResponseStatusCodeSame(200);
        self::assertGreaterThanOrEqual(3, $body['total']);
        self::assertSame('event.created', $body['items'][0]['action'], 'newest first');

        $devices = $this->auditPage('GET', '/api/admin/org/klub/audit-log?actor=device');
        self::assertSame(1, $devices['total']);
        $memberships = $this->auditPage('GET', '/api/admin/org/klub/audit-log?entityType=membership');
        self::assertSame(['membership.granted'], array_values(array_unique(array_column($memberships['items'], 'action'))));
    }

    public function test_access_follows_the_permission_matrix(): void
    {
        $this->record('event', 'ev-1', 'event.created', ActorType::User, '2026-11-01 10:00:00+00');
        $this->record('payment_request', 'pr-1', 'payment_request.created', ActorType::System, '2026-11-01 10:00:00+00');
        $this->member('manager@example.test', 'manager-club', Role::EventManager);
        $this->member('finance@example.test', 'finance-club', Role::Finance);
        $this->member('checkin@example.test', 'checkin-club', Role::CheckIn);

        $event = '/api/admin/org/klub/audit?entityType=event&entityId=ev-1';
        $payment = '/api/admin/org/klub/audit?entityType=payment_request&entityId=pr-1';

        $this->login('manager@example.test');
        $this->request('GET', $event);
        self::assertResponseStatusCodeSame(200);
        $this->request('GET', $payment);
        $this->assertProblem(403, 'forbidden');
        $this->request('GET', '/api/admin/org/klub/audit-log');
        $this->assertProblem(403, 'forbidden');

        $this->login('finance@example.test');
        $this->request('GET', $payment);
        self::assertResponseStatusCodeSame(200);
        $this->request('GET', $event);
        $this->assertProblem(403, 'forbidden');
        $this->request('GET', '/api/admin/org/klub/audit-log');
        $this->assertProblem(403, 'forbidden');

        $this->login('checkin@example.test');
        $this->request('GET', $event);
        $this->assertProblem(403, 'forbidden');
    }

    public function test_anonymous_is_401_and_a_foreign_organizer_is_404(): void
    {
        $this->createOwner('petr@example.test', 'cizi');
        $this->request('GET', '/api/admin/org/klub/audit?entityType=event&entityId=x');
        $this->assertProblem(401, 'unauthorized');
        $this->request('GET', '/api/admin/org/klub/audit-log');
        $this->assertProblem(401, 'unauthorized');

        $this->login('jana@example.test');
        $this->request('GET', '/api/admin/org/cizi/audit?entityType=event&entityId=x');
        $this->assertProblem(404, 'not_found');
        $this->request('GET', '/api/admin/org/cizi/audit-log');
        $this->assertProblem(404, 'not_found');
    }

    /**
     * @return array{items: list<array{id: string, entityType: string, entityId: string, action: string, actor: array{type: string, id: string|null, label: string|null}, correlationId: string, occurredAt: string}>, total: int, page: int, perPage: int}
     */
    private function auditPage(string $method, string $uri): array
    {
        $body = $this->request($method, $uri);

        /** @var array{items: list<array{id: string, entityType: string, entityId: string, action: string, actor: array{type: string, id: string|null, label: string|null}, correlationId: string, occurredAt: string}>, total: int, page: int, perPage: int} $body */
        return $body;
    }

    private function record(string $entityType, string $entityId, string $action, ActorType $actor, string $at, ?string $organizer = null): void
    {
        $this->service(AuditRecorder::class)->record(new AuditRecord(
            new AuditChange($entityType, $entityId, $action, null, after: ['k' => 'v']),
            new AuditActor($actor, 'USER' === $actor->value ? 'acc-1' : null, 'test'),
            'corr-'.$action,
            new \DateTimeImmutable($at),
            OrganizerId::fromString($organizer ?? $this->organizerId),
        ));
    }

    private function member(string $email, string $ownClub, Role $role): void
    {
        $account = $this->createOwner($email, $ownClub);
        MembershipFactory::createOne([
            'userAccountId' => UserAccountId::fromString($account['userAccountId']),
            'organizerId' => OrganizerId::fromString($this->organizerId),
            'role' => $role,
        ]);
    }
}
