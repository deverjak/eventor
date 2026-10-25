<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Audit;

use Eventor\Shared\Application\Security\IdentifiableUser;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Finds the actor of the work being dispatched: a signed-in team member, else an anonymous HTTP caller,
 * else the console. Worker-side actors come from the ActorStamp instead.
 */
final readonly class ActorResolver
{
    public function __construct(
        private Security $security,
        private RequestStack $requests,
    ) {}

    public function current(): AuditActor
    {
        $user = $this->security->getUser();
        if ($user instanceof IdentifiableUser) {
            return AuditActor::user($user->accountId());
        }

        return AuditActor::system(null !== $this->requests->getMainRequest() ? 'anonymous' : 'console');
    }
}
