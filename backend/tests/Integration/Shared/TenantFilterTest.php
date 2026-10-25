<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Identity\Domain\Membership\Membership;
use Eventor\Identity\Domain\Membership\Role;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Tenant\TenantNotSet;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterGuard;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Eventor\Tests\Support\Foundry\MembershipFactory;
use Eventor\Tests\Support\Foundry\OrganizerFactory;
use Eventor\Tests\Support\Foundry\UserAccountFactory;
use Eventor\Tests\Support\IntegrationTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * ADR 005: the Doctrine `organizer` filter. A TenantScoped entity (Membership) can only be read inside a tenant,
 * only for that tenant; "no tenant" is an error, never an empty or foreign result.
 */
final class TenantFilterTest extends IntegrationTestCase
{
    use Factories;

    private EntityManagerInterface $em;
    private OrganizerId $a;
    private OrganizerId $b;

    protected function setUp(): void
    {
        parent::setUp();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $this->a = OrganizerFactory::createOne()->id();
        $this->b = OrganizerFactory::createOne()->id();
        foreach ([$this->a, $this->a, $this->b] as $organizer) {
            MembershipFactory::createOne(['userAccountId' => UserAccountFactory::createOne()->id(), 'organizerId' => $organizer, 'role' => Role::Finance]);
        }
        $this->em->clear();
    }

    public function test_querying_a_tenant_scoped_entity_without_a_tenant_throws(): void
    {
        $this->expectException(TenantNotSet::class);

        $this->em->getRepository(Membership::class)->findAll();
    }

    public function test_only_the_active_tenants_rows_are_visible(): void
    {
        $tenant = $this->service(TenantContext::class);
        $sync = $this->service(TenantFilterSynchronizer::class);

        $tenant->enter($this->a);
        $sync->sync();
        self::assertCount(2, $this->em->getRepository(Membership::class)->findAll());

        $this->em->clear();
        $tenant->enter($this->b);
        $sync->sync();
        self::assertCount(1, $this->em->getRepository(Membership::class)->findAll());
    }

    public function test_leaving_the_tenant_closes_the_door_again(): void
    {
        $tenant = $this->service(TenantContext::class);
        $sync = $this->service(TenantFilterSynchronizer::class);

        $tenant->enter($this->a);
        $sync->sync();
        $this->em->getRepository(Membership::class)->findAll();

        $tenant->leave();
        $sync->sync();

        $this->expectException(TenantNotSet::class);
        $this->em->getRepository(Membership::class)->findAll();
    }

    public function test_cross_tenant_access_is_possible_only_through_the_logged_guard(): void
    {
        $guard = $this->service(TenantFilterGuard::class);

        $all = $guard->crossTenant('test: count memberships of every organizer', fn(): array => $this->em->getRepository(Membership::class)->findAll());
        self::assertCount(3, $all);

        $this->em->clear();
        $this->expectException(TenantNotSet::class);
        $this->em->getRepository(Membership::class)->findAll(); // the guard restored the closed filter
    }

    public function test_the_tenant_context_is_reset_between_requests(): void
    {
        $tenant = $this->service(TenantContext::class);
        $tenant->enter($this->a);

        $tenant->reset(); // what kernel.reset does in FrankenPHP worker mode

        self::assertNull($tenant->current());
    }
}
