<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Security;

use Eventor\Identity\Application\Access\OrganizerPolicyProvider;
use Eventor\Identity\Application\Access\PermissionMatrix;
use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\DevicePrincipal;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Contract\Security\EventPermissionSubject;
use Eventor\Identity\Contract\Security\OrganizerWidePermissionSubject;
use Eventor\Shared\Application\Tenant\TenantContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Role x permission decisions (chapter 08). The attribute is a `Permission` value; the subject is the current
 * `OrganizerView` (or nothing, meaning the active tenant). A check-in device holds the check-in role of its own
 * organizer, including the policy exception for cash. Event-level voters of other modules ask
 * `AccessPolicy::isAllowedForEvent()`, which applies the membership event scope.
 *
 * Event scope (ORG-03): an `EventPermissionSubject` is granted only for an event the membership covers, an
 * `OrganizerWidePermissionSubject` only to a membership without event restriction. A device is not event-scoped.
 *
 * @extends Voter<string, OrganizerView|EventPermissionSubject|OrganizerWidePermissionSubject|null>
 */
final class OrganizerPermissionVoter extends Voter
{
    public function __construct(
        private readonly AccessPolicy $policy,
        private readonly TenantContext $tenant,
        private readonly OrganizerPolicyProvider $policies,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute)
            && (null === $subject || $subject instanceof OrganizerView || $subject instanceof EventPermissionSubject || $subject instanceof OrganizerWidePermissionSubject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $organizer = match (true) {
            $subject instanceof OrganizerView => $subject->id,
            $subject instanceof EventPermissionSubject, $subject instanceof OrganizerWidePermissionSubject => $subject->organizer->id,
            default => $this->tenant->current(),
        };
        if (null === $user || null === $organizer) {
            return false;
        }

        $permission = Permission::from($attribute);
        if ($user instanceof DevicePrincipal) {
            return $user->organizerId()->equals($organizer)
                && PermissionMatrix::allowsDevice($permission, $this->policies->forOrganizer($organizer));
        }

        $identifier = $user->getUserIdentifier();

        return match (true) {
            $subject instanceof EventPermissionSubject => $this->policy->isAllowedForEvent($permission, $organizer, $subject->event, $identifier),
            $subject instanceof OrganizerWidePermissionSubject => $this->policy->isAllowed($permission, $organizer, $identifier)
                && [] === $this->policy->eventScope($organizer, $identifier),
            default => $this->policy->isAllowed($permission, $organizer, $identifier),
        };
    }
}
