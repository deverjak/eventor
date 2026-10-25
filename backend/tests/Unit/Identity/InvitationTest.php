<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Contract\Exception\InvitationNotFound;
use Eventor\Identity\Domain\Event\InvitationAccepted;
use Eventor\Identity\Domain\Event\InvitationCreated;
use Eventor\Identity\Domain\Event\InvitationRevoked;
use Eventor\Identity\Domain\Invitation\Invitation;
use Eventor\Identity\Domain\Membership\InvalidEventScope;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use PHPUnit\Framework\TestCase;

final class InvitationTest extends TestCase
{
    /**
     * @param list<EventId> $scope
     */
    private function invitation(Role $role = Role::Finance, array $scope = []): Invitation
    {
        return Invitation::create(InvitationId::generate(), OrganizerId::generate(), ' Eva@Example.test ', $role, $scope, str_repeat('a', 64), UserAccountId::generate(), new \DateTimeImmutable('2026-10-25T10:00:00+00:00'));
    }

    public function test_it_normalises_the_email_expires_after_seven_days_and_records_creation_without_the_email(): void
    {
        $invitation = $this->invitation();

        self::assertSame('eva@example.test', $invitation->email());
        self::assertSame('2026-11-01T10:00:00+00:00', $invitation->expiresAt()->format(\DATE_ATOM));
        $events = $invitation->releaseEvents();
        self::assertInstanceOf(InvitationCreated::class, $events[0]);
        self::assertStringNotContainsString('eva@example.test', json_encode($events[0]->auditChange(), \JSON_THROW_ON_ERROR));
    }

    public function test_it_is_pending_until_it_expires(): void
    {
        $invitation = $this->invitation();

        self::assertTrue($invitation->isPending(new \DateTimeImmutable('2026-11-01T09:59:59+00:00')));
        self::assertFalse($invitation->isPending(new \DateTimeImmutable('2026-11-01T10:00:00+00:00')));
    }

    public function test_accepting_consumes_it_once(): void
    {
        $invitation = $this->invitation();
        $invitation->releaseEvents();
        $now = new \DateTimeImmutable('2026-10-26T10:00:00+00:00');

        $invitation->accept(UserAccountId::generate(), $now);

        self::assertFalse($invitation->isPending($now));
        self::assertInstanceOf(InvitationAccepted::class, $invitation->releaseEvents()[0]);
        $this->expectException(InvitationNotFound::class);
        $invitation->accept(UserAccountId::generate(), $now);
    }

    public function test_an_expired_invitation_cannot_be_accepted(): void
    {
        $this->expectException(InvitationNotFound::class);

        $this->invitation()->accept(UserAccountId::generate(), new \DateTimeImmutable('2026-11-02T10:00:00+00:00'));
    }

    public function test_revoking_is_idempotent_and_blocks_acceptance(): void
    {
        $invitation = $this->invitation();
        $invitation->releaseEvents();
        $now = new \DateTimeImmutable('2026-10-26T10:00:00+00:00');

        $invitation->revoke($now);
        $invitation->revoke($now);

        self::assertCount(1, $invitation->releaseEvents(), 'one InvitationRevoked only');
        self::assertFalse($invitation->isPending($now));
    }

    public function test_an_accepted_invitation_cannot_be_revoked(): void
    {
        $invitation = $this->invitation();
        $invitation->accept(UserAccountId::generate(), new \DateTimeImmutable('2026-10-26T10:00:00+00:00'));

        $this->expectException(InvitationNotFound::class);
        $invitation->revoke(new \DateTimeImmutable('2026-10-27T10:00:00+00:00'));
    }

    public function test_the_event_scope_is_kept_for_scopable_roles_only(): void
    {
        $event = EventId::generate();
        $invitation = $this->invitation(Role::CheckIn, [$event]);

        self::assertTrue($invitation->eventScope()[0]->equals($event));

        $this->expectException(InvalidEventScope::class);
        $this->invitation(Role::Owner, [$event]);
    }

    public function test_revoked_event_class_exists_for_the_publisher(): void
    {
        self::assertTrue(class_exists(InvitationRevoked::class));
    }
}
