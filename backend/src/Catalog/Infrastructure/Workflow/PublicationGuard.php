<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Workflow;

use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Workflow\Transitions;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Audit\ActorType;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Guards of the `event_publication` state machine (chapter 05). Role decisions go through the voters, never through
 * role names: publishing and completing need `event.configure`, cancelling `event.cancel` (owner). A system actor
 * (job, subscriber) is trusted. Completing is possible only after the event has ended.
 */
#[AsGuardListener(workflow: 'event_publication')]
final readonly class PublicationGuard
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    public function __invoke(GuardEvent $event): void
    {
        $subject = $event->getSubject();
        $transition = $event->getTransition()?->getName();
        if (!$subject instanceof Event || null === $transition) {
            return;
        }

        $permission = 'cancel' === $transition ? Permission::EventCancel : Permission::EventConfigure;
        if (ActorType::User === $this->audit->actor()?->type && !$this->authorization->isGranted($permission->value)) {
            $event->addTransitionBlocker(new TransitionBlocker('You do not have the permission for this change.', Transitions::FORBIDDEN));

            return;
        }

        if ('complete' === $transition && $subject->endsAt() > $this->clock->now()) {
            $event->addTransitionBlocker(new TransitionBlocker('The event has not ended yet.', 'event_not_finished'));
        }
    }
}
