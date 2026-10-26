<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\Security;

use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Tenant\TenantContext;
use Eventor\Shared\Domain\Audit\ActorType;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Infrastructure\Doctrine\TenantFilterSynchronizer;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * A self-service token belongs to exactly one organizer, so authenticating it activates that tenant (TenantContext and the
 * Doctrine `organizer` filter) and names the actor of everything the request does: `participant_token` with the token id
 * (AUD-03), never the person's name.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final readonly class SelfServiceContextListener
{
    public function __construct(
        private TenantContext $tenant,
        private TenantFilterSynchronizer $filter,
        private AuditContext $audit,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SelfServicePrincipal) {
            return;
        }

        $this->tenant->enter($user->organizerId);
        $this->filter->sync();
        $this->audit->start(new AuditActor(ActorType::ParticipantToken, $user->tokenId->toString()), null);
    }
}
