<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Domain\Account\UserAccount;
use Eventor\Identity\Domain\Event\MembershipGranted;
use Eventor\Identity\Domain\Event\OrganizerRegistered;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Identity\Domain\Organizer\Organizer;
use Eventor\Shared\Domain\Id\MembershipId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use PHPUnit\Framework\TestCase;

final class IdentityDomainTest extends TestCase
{
    public function test_an_account_normalises_its_email(): void
    {
        $account = UserAccount::register(UserAccountId::generate(), '  Jana.Novakova@Example.CZ ', 'hash', 'Jana', new \DateTimeImmutable());

        self::assertSame('jana.novakova@example.cz', $account->email());
        self::assertFalse($account->isDisabled());
    }

    public function test_registering_an_organizer_records_an_audited_event_without_personal_data(): void
    {
        $id = OrganizerId::generate();
        $organizer = Organizer::register($id, 'klub-plzen', 'Klub Plzeň', new \DateTimeImmutable());

        $events = $organizer->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizerRegistered::class, $events[0]);
        $change = $events[0]->auditChange();
        self::assertSame('organizer.registered', $change->action);
        self::assertSame($id->toString(), $change->entityId);
        self::assertTrue($id->equals($change->organizerId ?? OrganizerId::generate()));
        self::assertSame([], $organizer->releaseEvents(), 'events are released once');
    }

    public function test_granting_a_membership_records_an_event_and_is_tenant_scoped(): void
    {
        $organizerId = OrganizerId::generate();
        $membership = Membership::grant(MembershipId::generate(), UserAccountId::generate(), $organizerId, Role::Finance, new \DateTimeImmutable());

        self::assertTrue($organizerId->equals($membership->organizerId()));
        $events = $membership->releaseEvents();
        self::assertInstanceOf(MembershipGranted::class, $events[0]);
        self::assertSame(['userAccountId' => $membership->userAccountId()->toString(), 'role' => 'FINANCE'], $events[0]->auditChange()->after);
    }
}
