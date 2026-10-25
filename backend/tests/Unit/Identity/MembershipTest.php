<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Identity\Domain\Event\MembershipRevoked;
use Eventor\Identity\Domain\Event\MembershipRoleChanged;
use Eventor\Identity\Domain\Membership\InvalidEventScope;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use PHPUnit\Framework\TestCase;

final class MembershipTest extends TestCase
{
    private const string NOW = '2026-10-25T10:00:00+00:00';

    /**
     * @param list<EventId> $scope
     */
    private function grant(Role $role, array $scope = []): Membership
    {
        return Membership::grant(MembershipId::generate(), UserAccountId::generate(), OrganizerId::generate(), $role, new \DateTimeImmutable(self::NOW), $scope);
    }

    public function test_an_unrestricted_membership_may_access_every_event(): void
    {
        $membership = $this->grant(Role::EventManager);

        self::assertSame([], $membership->eventScope());
        self::assertTrue($membership->mayAccessEvent(EventId::generate()));
    }

    public function test_a_restricted_membership_may_access_only_its_events(): void
    {
        $mine = EventId::generate();
        $membership = $this->grant(Role::CheckIn, [$mine, EventId::generate(), $mine]);

        self::assertCount(2, $membership->eventScope(), 'duplicates collapse');
        self::assertTrue($membership->mayAccessEvent($mine));
        self::assertFalse($membership->mayAccessEvent(EventId::generate()));
    }

    public function test_only_event_manager_and_check_in_can_be_restricted(): void
    {
        foreach ([Role::Owner, Role::Finance] as $role) {
            try {
                $this->grant($role, [EventId::generate()]);
                self::fail("$role->value must not accept a scope");
            } catch (InvalidEventScope) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_granting_records_the_event_with_the_scope(): void
    {
        $event = EventId::generate();
        $events = $this->grant(Role::EventManager, [$event])->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(MembershipGranted::class, $events[0]);
        self::assertSame(Role::EventManager, $events[0]->role);
        self::assertTrue($events[0]->eventScope[0]->equals($event));
    }

    public function test_changing_replaces_role_and_scope_and_records_before_and_after(): void
    {
        $a = EventId::generate();
        $b = EventId::generate();
        $membership = $this->grant(Role::EventManager, [$a]);
        $membership->releaseEvents();

        $membership->change(Role::CheckIn, [$b]);

        self::assertSame(Role::CheckIn, $membership->role());
        self::assertCount(1, $membership->eventScope());
        self::assertTrue($membership->mayAccessEvent($b));
        self::assertFalse($membership->mayAccessEvent($a));
        $events = $membership->releaseEvents();
        self::assertInstanceOf(MembershipRoleChanged::class, $events[0]);
        self::assertSame(Role::EventManager, $events[0]->previousRole);
        self::assertSame(Role::CheckIn, $events[0]->role);
        self::assertTrue($events[0]->previousScope[0]->equals($a));
    }

    public function test_changing_to_a_role_that_sees_everything_refuses_a_scope_and_keeps_the_state(): void
    {
        $a = EventId::generate();
        $membership = $this->grant(Role::EventManager, [$a]);

        try {
            $membership->change(Role::Finance, [$a]);
            self::fail('Finance cannot be scoped');
        } catch (InvalidEventScope) {
        }

        self::assertSame(Role::EventManager, $membership->role());
        self::assertTrue($membership->mayAccessEvent($a));
    }

    public function test_revoking_records_the_event_with_the_last_role(): void
    {
        $membership = $this->grant(Role::Finance);
        $membership->releaseEvents();

        $membership->revoke();

        $events = $membership->releaseEvents();
        self::assertInstanceOf(MembershipRevoked::class, $events[0]);
        self::assertSame(Role::Finance, $events[0]->role);
    }
}
