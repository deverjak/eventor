<?php

declare(strict_types=1);

namespace Eventor\Identity\Infrastructure\Security;

use Eventor\Identity\Contract\DevicePrincipal;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * A device belongs to exactly one organizer, so authenticating it activates that tenant (TenantContext + Doctrine
 * `organizer` filter) for the rest of the request, like `#[CurrentOrganizer]` does for a team member.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final readonly class DeviceTenantListener
{
    public function __construct(
        private TenantContext $tenant,
        private TenantFilterSynchronizer $filter,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof DevicePrincipal) {
            $this->tenant->enter($user->organizerId());
            $this->filter->sync();
        }
    }
}
