<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Application\Access\OrganizerPolicy as AccessPolicyFlags;
use Eventor\Identity\Application\Access\PermissionMatrix;
use Eventor\Identity\Application\DeviceToken\DeviceTokenFormat;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\DeviceToken\DeviceToken;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenAlreadyExpired;
use Eventor\Identity\Domain\DeviceToken\DeviceTokenId;
use Eventor\Identity\Domain\Event\OrganizerPolicyChanged;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Identity\Domain\Organizer\InvalidPolicyValue;
use Eventor\Identity\Domain\Organizer\OrganizerPolicy;
use Eventor\Identity\Infrastructure\Security\DeviceRequestMatcher;
use Eventor\Identity\Infrastructure\Security\HmacInvitationTokens;
use Eventor\Shared\Domain\Id\InvitationId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\UserAccountId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class OrganizerPolicyAndTokensTest extends TestCase
{
    public function test_a_new_policy_has_the_documented_defaults(): void
    {
        $snapshot = OrganizerPolicy::defaultsFor(OrganizerId::generate())->snapshot();

        self::assertSame(14, $snapshot['defaultDueDays']);
        self::assertSame(48, $snapshot['defaultHoldTtlHours']);
        self::assertSame('ask', $snapshot['overpaymentHandling']);
        self::assertFalse($snapshot['autoRestoreLatePayment']);
        self::assertFalse($snapshot['checkInMayRecordCash']);
        self::assertFalse($snapshot['eventManagerMayResolvePayments']);
    }

    public function test_changing_the_policy_records_before_and_after(): void
    {
        $policy = OrganizerPolicy::defaultsFor(OrganizerId::generate());

        $policy->change(true, true, true, \Eventor\Identity\Contract\OverpaymentHandling::Refund, 7, 24);

        $events = $policy->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(OrganizerPolicyChanged::class, $events[0]);
        self::assertSame(14, $events[0]->before['defaultDueDays']);
        self::assertSame(7, $events[0]->after['defaultDueDays']);
        self::assertSame('refund', $events[0]->after['overpaymentHandling']);
    }

    #[DataProvider('invalidPolicies')]
    public function test_out_of_range_values_are_refused_and_nothing_changes(int $dueDays, int $holdHours): void
    {
        $policy = OrganizerPolicy::defaultsFor(OrganizerId::generate());

        try {
            $policy->change(false, false, false, \Eventor\Identity\Contract\OverpaymentHandling::Ask, $dueDays, $holdHours);
            self::fail('expected InvalidPolicyValue');
        } catch (InvalidPolicyValue) {
        }

        self::assertSame(14, $policy->snapshot()['defaultDueDays']);
        self::assertSame([], $policy->releaseEvents());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidPolicies(): iterable
    {
        yield 'zero due days' => [0, 48];
        yield 'too many due days' => [366, 48];
        yield 'zero hold hours' => [14, 0];
        yield 'too many hold hours' => [14, 721];
    }

    public function test_the_conditional_cells_follow_the_policy_flags(): void
    {
        self::assertTrue(PermissionMatrix::dependsOnPolicy(Role::CheckIn, Permission::CashRecord));
        self::assertFalse(PermissionMatrix::dependsOnPolicy(Role::Finance, Permission::CashRecord));
        self::assertFalse(PermissionMatrix::dependsOnPolicy(Role::CheckIn, Permission::EventEdit));

        self::assertFalse(PermissionMatrix::allows(Role::CheckIn, Permission::CashRecord, new AccessPolicyFlags()));
        self::assertTrue(PermissionMatrix::allows(Role::CheckIn, Permission::CashRecord, new AccessPolicyFlags(checkInMayRecordCash: true)));
        self::assertFalse(PermissionMatrix::allows(Role::EventManager, Permission::PaymentExceptionResolve, new AccessPolicyFlags(checkInMayRecordCash: true)));
        self::assertTrue(PermissionMatrix::allows(Role::EventManager, Permission::PaymentExceptionResolve, new AccessPolicyFlags(eventManagerMayResolvePayments: true)));
    }

    public function test_a_device_holds_the_check_in_role(): void
    {
        $off = new AccessPolicyFlags();
        self::assertTrue(PermissionMatrix::allowsDevice(Permission::CheckInPerform, $off));
        self::assertTrue(PermissionMatrix::allowsDevice(Permission::ParticipantsViewCheckIn, $off));
        self::assertFalse(PermissionMatrix::allowsDevice(Permission::ParticipantsViewFull, $off));
        self::assertFalse(PermissionMatrix::allowsDevice(Permission::TeamManage, $off));
        self::assertFalse(PermissionMatrix::allowsDevice(Permission::CashRecord, $off));
        self::assertTrue(PermissionMatrix::allowsDevice(Permission::CashRecord, new AccessPolicyFlags(checkInMayRecordCash: true)));
    }

    public function test_the_device_token_format_round_trips_and_rejects_garbage(): void
    {
        $id = DeviceTokenId::generate();
        $secret = DeviceTokenFormat::newSecret();
        $token = DeviceTokenFormat::format($id, $secret);

        $parsed = DeviceTokenFormat::parse($token);
        self::assertNotNull($parsed);
        self::assertTrue($parsed['id']->equals($id));
        self::assertSame($secret, $parsed['secret']);
        self::assertSame(64, \strlen(DeviceTokenFormat::hash($secret)));
        self::assertNotSame($secret, DeviceTokenFormat::newSecret());

        foreach (['', 'dev1_', 'dev1_x_y', 'dev2_'.$id->toString().'_'.$secret, $token.'x', 'Bearer '.$token] as $garbage) {
            self::assertNull(DeviceTokenFormat::parse($garbage), $garbage);
        }
    }

    public function test_invitation_tokens_are_deterministic_per_id_and_bound_to_the_secret(): void
    {
        $tokens = new HmacInvitationTokens('secret-one');
        $id = InvitationId::generate();

        $token = $tokens->issue($id);
        self::assertSame($token, $tokens->issue($id), 'rebuildable at delivery time');
        self::assertNotSame($token, $tokens->issue(InvitationId::generate()));
        self::assertNotSame($token, (new HmacInvitationTokens('secret-two'))->issue($id));
        self::assertTrue($tokens->idOf($token)?->equals($id) ?? false);
        self::assertNull($tokens->idOf('inv1_garbage'));
        self::assertNull($tokens->idOf($token.'x'));
        self::assertSame(hash('sha256', $token), $tokens->hash($token));
    }

    public function test_a_device_token_cannot_expire_in_the_past(): void
    {
        $now = new \DateTimeImmutable('2026-10-25T10:00:00+00:00');

        $this->expectException(DeviceTokenAlreadyExpired::class);
        DeviceToken::issue(DeviceTokenId::generate(), OrganizerId::generate(), null, 'Late', str_repeat('a', 64), UserAccountId::generate(), $now, $now);
    }

    public function test_a_device_token_is_active_until_revoked_or_expired_and_revoking_is_idempotent(): void
    {
        $now = new \DateTimeImmutable('2026-10-25T10:00:00+00:00');
        $token = DeviceToken::issue(DeviceTokenId::generate(), OrganizerId::generate(), null, 'Gate', str_repeat('a', 64), UserAccountId::generate(), $now, $now->modify('+1 day'));
        $token->releaseEvents();

        self::assertTrue($token->isActive($now));
        self::assertFalse($token->isActive($now->modify('+1 day')));

        $token->revoke($now);
        $token->revoke($now);
        self::assertFalse($token->isActive($now));
        self::assertCount(1, $token->releaseEvents());
    }

    public function test_the_check_in_firewall_matches_only_device_requests_under_check_in(): void
    {
        $matcher = new DeviceRequestMatcher();
        $device = ['HTTP_AUTHORIZATION' => 'Bearer dev1_x'];

        self::assertTrue($matcher->matches(Request::create('/api/check-in/whoami', server: $device)));
        self::assertTrue($matcher->matches(Request::create('/api/check-in', server: $device)));
        self::assertFalse($matcher->matches(Request::create('/api/check-in/whoami')), 'a team member without a token');
        self::assertFalse($matcher->matches(Request::create('/api/check-in/whoami', server: ['HTTP_AUTHORIZATION' => 'Bearer other'])));
        self::assertFalse($matcher->matches(Request::create('/api/admin/org/x', server: $device)));
        self::assertFalse($matcher->matches(Request::create('/api/check-in-evil', server: $device)));
    }
}
