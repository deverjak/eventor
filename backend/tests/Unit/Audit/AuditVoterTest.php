<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Audit;

use Eventor\Audit\Contract\AuditActorKind;
use Eventor\Audit\UserInterface\Security\AuditScope;
use Eventor\Audit\UserInterface\Security\AuditVoter;
use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Domain\Audit\ActorType;
use Eventor\Shared\Domain\Id\OrganizerId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class AuditVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{list<Permission>, ?string, bool}>
     */
    public static function cases(): iterable
    {
        yield 'owner reads the whole log' => [[Permission::AuditViewAll], null, true];
        yield 'owner reads a finance entity' => [[Permission::AuditViewAll], 'payment_request', true];
        yield 'finance cannot read the organizer-wide log' => [[Permission::AuditViewFinance], null, false];
        yield 'finance reads payments' => [[Permission::AuditViewFinance], 'payment_request', true];
        yield 'finance reads refunds' => [[Permission::AuditViewFinance], 'refund_task', true];
        yield 'finance does not read events' => [[Permission::AuditViewFinance], 'event', false];
        yield 'event manager reads events' => [[Permission::AuditViewOwnEvents], 'event', true];
        yield 'event manager does not read payments' => [[Permission::AuditViewOwnEvents], 'payment_request', false];
        yield 'event manager cannot read the organizer-wide log' => [[Permission::AuditViewOwnEvents], null, false];
        yield 'a member without audit permissions reads nothing' => [[], 'event', false];
    }

    /**
     * @param list<Permission> $granted
     */
    #[DataProvider('cases')]
    public function test_it_decides_by_permission_and_entity_type(array $granted, ?string $entityType, bool $expected): void
    {
        $policy = self::createStub(AccessPolicy::class);
        $policy->method('isAllowed')->willReturnCallback(static fn(Permission $permission): bool => \in_array($permission, $granted, true));
        $token = new UsernamePasswordToken(new InMemoryUser('user-1', null), 'main');

        $vote = (new AuditVoter($policy))->vote($token, new AuditScope(OrganizerId::generate(), $entityType), [AuditScope::ATTRIBUTE]);

        self::assertSame($expected ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $vote);
    }

    public function test_it_abstains_for_other_attributes_and_subjects(): void
    {
        $policy = self::createStub(AccessPolicy::class);
        $token = new UsernamePasswordToken(new InMemoryUser('user-1', null), 'main');
        $voter = new AuditVoter($policy);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new AuditScope(OrganizerId::generate()), ['something.else']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, 'not a scope', [AuditScope::ATTRIBUTE]));
    }

    public function test_every_actor_kind_maps_to_a_stored_actor_type(): void
    {
        $stored = array_map(static fn(ActorType $type): string => $type->value, ActorType::cases());

        foreach (AuditActorKind::cases() as $kind) {
            self::assertContains($kind->storedValue(), $stored, $kind->name);
        }
        self::assertCount(\count(ActorType::cases()), AuditActorKind::cases(), 'every stored actor type is filterable');
    }
}
