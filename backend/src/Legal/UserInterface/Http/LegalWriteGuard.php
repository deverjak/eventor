<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http;

use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\OrganizerView;
use Eventor\Identity\Contract\Permission;
use Eventor\Legal\Contract\DocumentLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Who may change legal documents: event and activity documents need `legal.event.manage` (owner, event manager,
 * checked by the route), the organizer's default documents additionally `legal.organizer.publish` (owner only).
 */
final readonly class LegalWriteGuard
{
    public function __construct(
        private AccessPolicy $policy,
        private Security $security,
    ) {}

    public function assertMayChange(OrganizerView $organizer, DocumentLevel $level): void
    {
        if (DocumentLevel::Organizer !== $level) {
            return;
        }

        $user = $this->security->getUser();
        if (null === $user || !$this->policy->isAllowed(Permission::OrganizerLegalPublish, $organizer->id, $user->getUserIdentifier())) {
            throw new AccessDeniedException('Missing permission "legal.organizer.publish".');
        }
    }
}
