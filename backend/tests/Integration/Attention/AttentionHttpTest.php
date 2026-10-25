<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Attention;

use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Application\Attention\AttentionRaiser;
use Eventor\Shared\Application\Attention\AttentionRequest;
use Eventor\Shared\Application\Attention\AttentionType;
use Eventor\Shared\Domain\Id\AttentionItemId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\HttpTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The admin API of the queue: list, detail with actions, executing an action, counts (ADM-03).
 */
final class AttentionHttpTest extends HttpTestCase
{
    use Factories;

    private const string BASE = '/api/admin/org/klub/attention';

    private string $organizerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizerId = $this->createOwner('jana@example.test', 'klub')['organizerId'];
    }

    public function test_the_empty_queue_is_a_200_with_no_items(): void
    {
        $this->login('jana@example.test');

        $body = $this->listing('GET', self::BASE);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['items' => [], 'page' => 1, 'perPage' => 25, 'total' => 0], $body);
        $summary = $this->summary('GET', self::BASE.'/summary');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $summary['unresolved']);
    }

    public function test_the_list_filters_by_type_state_and_event_and_pages(): void
    {
        $this->login('jana@example.test');
        $event = '0199a000-0000-7000-8000-0000000000e1';
        $this->raise(AttentionType::LatePayment, 'participation', 'p1');
        $this->raise(AttentionType::Overpayment, 'order', 'o1');
        $this->raise(AttentionType::RefundTask, 'refund_task', 'r1');
        $this->connection()->executeStatement("UPDATE attention.attention_item SET state = 'resolved' WHERE type = 'refund_task'");
        $this->connection()->executeStatement('UPDATE attention.attention_item SET event_id = ? WHERE type = \'overpayment\'', [$event]);

        $all = $this->listing('GET', self::BASE);
        self::assertSame(3, $all['total']);

        $byType = $this->listing('GET', self::BASE.'?type=late_payment');
        self::assertSame(['late_payment'], array_column($byType['items'], 'type'));

        $byState = $this->listing('GET', self::BASE.'?state=resolved');
        self::assertSame(['refund_task'], array_column($byState['items'], 'type'));

        $byEvent = $this->listing('GET', self::BASE.'?eventId='.$event);
        self::assertSame(['overpayment'], array_column($byEvent['items'], 'type'));

        $page = $this->listing('GET', self::BASE.'?perPage=2&page=2');
        self::assertCount(1, $page['items']);
        self::assertSame(3, $page['total']);

        $summary = $this->summary('GET', self::BASE.'/summary');
        self::assertSame(2, $summary['unresolved']);
        self::assertSame(['late_payment' => 1, 'overpayment' => 1], $summary['byType']);
        $scoped = $this->summary('GET', self::BASE.'/summary?eventId='.$event);
        self::assertSame(['overpayment' => 1], $scoped['byType']);
    }

    public function test_invalid_filters_are_422(): void
    {
        $this->login('jana@example.test');

        foreach (['?type=nonsense', '?state=nonsense', '?eventId=not-a-uuid', '?page=0', '?perPage=1000'] as $query) {
            $this->request('GET', self::BASE.$query);
            $this->assertProblem(422, 'validation_failed');
        }
        $this->request('GET', self::BASE.'/summary?eventId=nope');
        $this->assertProblem(422, 'validation_failed');
    }

    public function test_the_detail_lists_the_actions_of_the_type(): void
    {
        $this->login('jana@example.test');
        $id = $this->raise(AttentionType::UnmatchedPayment, 'bank_transaction', 'tx-1');

        $body = $this->item('GET', self::BASE.'/'.$id);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('unmatched_payment', $body['type']);
        self::assertSame('open', $body['state']);
        $actions = array_column($body['actions'], null, 'key');
        self::assertSame(['allocate_transaction', 'acknowledge', 'resolve', 'dismiss'], array_keys($actions));
        self::assertSame('link', $actions['allocate_transaction']['kind']);
        self::assertSame(['resource' => 'bank_transaction', 'id' => 'tx-1'], $actions['allocate_transaction']['target']);
        self::assertSame('command', $actions['resolve']['kind']);
        self::assertTrue($actions['resolve']['noteAllowed']);
    }

    public function test_acknowledge_then_resolve_with_a_note_leaves_an_audit_trail(): void
    {
        $this->login('jana@example.test');
        $id = $this->raise(AttentionType::DeliveryFailed, 'campaign', 'c1');

        $ack = $this->item('POST', self::BASE.'/'.$id.'/actions/acknowledge', []);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('acknowledged', $ack['state']);
        self::assertNotNull($ack['acknowledgedAt']);
        self::assertNotContains('acknowledge', array_column($ack['actions'], 'key'));

        $done = $this->item('POST', self::BASE.'/'.$id.'/actions/resolve', ['note' => '  called them  ']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('resolved', $done['state']);
        self::assertSame('called them', $done['resolutionNote']);
        self::assertSame('action', $done['resolution']);
        self::assertSame([], $done['actions']);

        self::assertSame(
            ['attention.acknowledged', 'attention.resolved'],
            $this->dbColumn("SELECT action FROM audit.audit_entry WHERE entity_type = 'attention_item' AND entity_id = ? ORDER BY occurred_at, id", [$id]),
        );
        $actor = $this->dbRow("SELECT actor_type FROM audit.audit_entry WHERE entity_id = ? AND action = 'attention.resolved'", [$id]);
        self::assertSame('USER', $actor['actor_type']);
    }

    public function test_dismiss_works_without_a_body(): void
    {
        $this->login('jana@example.test');
        $id = $this->raise(AttentionType::IntegrationDegraded, 'integration', 'pairrot');

        $body = $this->item('POST', self::BASE.'/'.$id.'/actions/dismiss');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('dismissed', $body['state']);
    }

    public function test_a_closed_item_and_a_link_or_unknown_action_are_422(): void
    {
        $this->login('jana@example.test');
        $id = $this->raise(AttentionType::RefundTask, 'refund_task', 'r1');

        $this->request('POST', self::BASE.'/'.$id.'/actions/settle_refund', []);
        $this->assertProblem(422, 'attention_action_not_available');
        $this->request('POST', self::BASE.'/'.$id.'/actions/launch_missiles', []);
        $this->assertProblem(422, 'attention_action_not_available');

        $this->request('POST', self::BASE.'/'.$id.'/actions/resolve', []);
        self::assertResponseStatusCodeSame(200);
        $this->request('POST', self::BASE.'/'.$id.'/actions/resolve', []);
        $this->assertProblem(422, 'attention_action_not_available');
        $this->request('POST', self::BASE.'/'.$id.'/actions/acknowledge', []);
        $this->assertProblem(422, 'attention_action_not_available');
    }

    public function test_a_too_long_note_is_422(): void
    {
        $this->login('jana@example.test');
        $id = $this->raise(AttentionType::LatePayment, 'participation', 'p1');

        $this->request('POST', self::BASE.'/'.$id.'/actions/resolve', ['note' => str_repeat('x', 1001)]);

        $this->assertProblem(422, 'validation_failed');
    }

    public function test_an_unknown_item_is_404(): void
    {
        $this->login('jana@example.test');
        $unknown = AttentionItemId::generate()->toString();

        $this->request('GET', self::BASE.'/'.$unknown);
        $this->assertProblem(404, 'attention_item_not_found');
        $this->request('POST', self::BASE.'/'.$unknown.'/actions/resolve', []);
        $this->assertProblem(404, 'attention_item_not_found');
    }

    public function test_an_item_of_another_organizer_is_404_and_untouched(): void
    {
        $other = $this->createOwner('petr@example.test', 'cizi-klub')['organizerId'];
        $foreign = $this->raise(AttentionType::LatePayment, 'participation', 'p-foreign', $other);
        $this->login('jana@example.test');

        $this->request('GET', self::BASE.'/'.$foreign);
        $this->assertProblem(404, 'attention_item_not_found');
        $this->request('POST', self::BASE.'/'.$foreign.'/actions/resolve', []);
        $this->assertProblem(404, 'attention_item_not_found');
        self::assertSame('open', $this->dbString('SELECT state FROM attention.attention_item WHERE id = ?', [$foreign]));

        $list = $this->listing('GET', self::BASE);
        self::assertSame(0, $list['total']);
    }

    public function test_a_foreign_organizer_is_404_and_anonymous_is_401(): void
    {
        $this->createOwner('petr@example.test', 'cizi-klub');
        $this->request('GET', self::BASE);
        $this->assertProblem(401, 'unauthorized');

        $this->login('jana@example.test');
        $this->request('GET', '/api/admin/org/cizi-klub/attention');
        $this->assertProblem(404, 'not_found');
        $this->request('POST', '/api/admin/org/cizi-klub/attention/'.AttentionItemId::generate()->toString().'/actions/resolve', []);
        $this->assertProblem(404, 'not_found');
    }

    public function test_check_in_staff_cannot_see_the_queue_and_an_event_manager_cannot_resolve(): void
    {
        $id = $this->raise(AttentionType::LatePayment, 'participation', 'p1');
        $this->member('checkin@example.test', 'checkin-club', Role::CheckIn);
        $this->member('manager@example.test', 'manager-club', Role::EventManager);

        $this->login('checkin@example.test');
        $this->request('GET', self::BASE);
        $this->assertProblem(403, 'forbidden');

        $this->login('manager@example.test');
        $this->request('GET', self::BASE);
        self::assertResponseStatusCodeSame(200);
        $this->request('POST', self::BASE.'/'.$id.'/actions/acknowledge', []);
        self::assertResponseStatusCodeSame(200);
        $this->request('POST', self::BASE.'/'.$id.'/actions/resolve', []);
        $this->assertProblem(403, 'forbidden');
        self::assertSame('acknowledged', $this->dbString('SELECT state FROM attention.attention_item WHERE id = ?', [$id]));
    }

    public function test_a_post_without_the_csrf_header_is_refused(): void
    {
        $this->login('jana@example.test');
        $id = $this->raise(AttentionType::LatePayment, 'participation', 'p1');

        $this->request('POST', self::BASE.'/'.$id.'/actions/resolve', [], csrf: false);

        $this->assertProblem(403, 'csrf_invalid');
    }

    /**
     * @return array{items: list<array<string, mixed>>, page: int, perPage: int, total: int}
     */
    private function listing(string $method, string $uri): array
    {
        $body = $this->request($method, $uri);

        /** @var array{items: list<array<string, mixed>>, page: int, perPage: int, total: int} $body */
        return $body;
    }

    /**
     * @return array{unresolved: int, byType: array<string, int>}
     */
    private function summary(string $method, string $uri): array
    {
        $body = $this->request($method, $uri);

        /** @var array{unresolved: int, byType: array<string, int>} $body */
        return $body;
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array{type: string, state: string, acknowledgedAt: string|null, resolution: string|null, resolutionNote: string|null, actions: list<array{key: string, kind: string, noteAllowed: bool, target: array{resource: string, id: string}|null}>}
     */
    private function item(string $method, string $uri, ?array $json = null): array
    {
        $body = $this->request($method, $uri, $json);

        /** @var array{type: string, state: string, acknowledgedAt: string|null, resolution: string|null, resolutionNote: string|null, actions: list<array{key: string, kind: string, noteAllowed: bool, target: array{resource: string, id: string}|null}>} $body */
        return $body;
    }

    private function raise(AttentionType $type, string $subjectType, string $subjectId, ?string $organizer = null): string
    {
        $organizer ??= $this->organizerId;
        $this->service(AttentionRaiser::class)->raise(new AttentionRequest($type, "$type->value:$subjectType:$subjectId", OrganizerId::fromString($organizer), $subjectType, $subjectId, ['orderId' => 'order-1']));

        return $this->dbString('SELECT id FROM attention.attention_item WHERE dedupe_key = ?', ["$type->value:$subjectType:$subjectId"]);
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
