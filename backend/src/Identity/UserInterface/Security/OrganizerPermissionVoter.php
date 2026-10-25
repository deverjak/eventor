<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Security;

use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Tenant\TenantContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Role x permission decisions (chapter 08). The attribute is a `Permission` value; the subject is the current
 * `OrganizerView` (or nothing, meaning the active tenant). Event-level voters of other modules ask `AccessPolicy`
 * the same way.
 *
 * @extends Voter<string, OrganizerView|null>
 */
final class OrganizerPermissionVoter extends Voter
{
    public function __construct(
        private readonly AccessPolicy $policy,
        private readonly TenantContext $tenant,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute) && (null === $subject || $subject instanceof OrganizerView);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        $organizer = $subject instanceof OrganizerView ? $subject->id : $this->tenant->current();
        if (null === $user || null === $organizer) {
            return false;
        }

        return $this->policy->isAllowed(Permission::from($attribute), $organizer, $user->getUserIdentifier());
    }
}
