<?php

declare(strict_types=1);

namespace Eventor\Catalog\Infrastructure\Workflow;

use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Workflow\Transitions;
use Eventor\Identity\Contract\Permission;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Audit\ActorType;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Guards of the `registration_window` state machine of events and activities (EVT-04, chapter 05): registration can be
 * opened or resumed only for a published event, and an activity only while the registration of its event is open.
 * Changing the window needs `event.configure` (voter); a system actor is trusted.
 */
#[AsGuardListener(workflow: 'registration_window')]
final readonly class RegistrationWindowGuard
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private AuditContext $audit,
        private EventRepository $events,
    ) {}

    /**
     * @param GuardEvent<object> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        $subject = $event->getSubject();
        $transition = $event->getTransition()->getName();
        if (!($subject instanceof Event || $subject instanceof Activity)) {
            return;
        }

        if (ActorType::User === $this->audit->actor()?->type && !$this->authorization->isGranted(Permission::EventConfigure->value)) {
            $event->addTransitionBlocker(new TransitionBlocker('You do not have the permission for this change.', Transitions::FORBIDDEN));

            return;
        }

        if ('open' !== $transition && 'resume' !== $transition) {
            return;
        }

        $owner = $subject instanceof Event ? $subject : $this->events->get($subject->eventId());
        if (PublicationState::Published !== $owner->publicationState()) {
            $event->addTransitionBlocker(new TransitionBlocker('Registration can only be opened for a published event.', 'event_not_published'));

            return;
        }
        if ($subject instanceof Activity && RegistrationState::Open !== $owner->registrationState()) {
            $event->addTransitionBlocker(new TransitionBlocker('Registration of an activity can only be opened while the registration of the event is open.', 'event_registration_not_open'));
        }
    }
}
