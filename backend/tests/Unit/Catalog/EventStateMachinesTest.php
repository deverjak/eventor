<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Catalog\Domain\Event\PublicationChanged;
use Eventor\Catalog\Domain\Event\RegistrationWindowChanged;
use Eventor\Catalog\Domain\Exception\TransitionForbidden;
use Eventor\Catalog\Domain\Exception\TransitionNotAllowed;
use Eventor\Catalog\Domain\Workflow\Transitions;
use Eventor\Tests\Support\Catalog\Build;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * The two state machines of chapter 05 on the real entities (the same transition tables as in services.php), without
 * the framework: legal transitions, refused ones, guard blockers, and the domain events each transition records.
 */
final class EventStateMachinesTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function test_an_event_walks_draft_published_completed_archived(): void
    {
        $event = Build::event();
        $activity = Build::activity($event);
        $machine = $this->publication();

        $event->transitionPublication($machine, 'publish', [$activity->id()], null, []);
        self::assertSame(PublicationState::Published, $event->publicationState());
        self::assertTrue($event->isLive());

        $event->transitionPublication($machine, 'complete', [$activity->id()], null, []);
        $event->transitionPublication($machine, 'archive', [], null, []);
        self::assertSame(PublicationState::Archived, $event->publicationState());

        $recorded = array_values(array_filter($event->releaseEvents(), static fn(object $e): bool => $e instanceof PublicationChanged));
        self::assertSame(['published', 'completed', 'archived'], array_map(static fn(PublicationChanged $e): string => $e->to->value, $recorded));
        self::assertSame([$activity->id()], $recorded[0]->activityIds);
    }

    public function test_cancelling_keeps_the_reason_and_is_final_except_for_the_archive(): void
    {
        $event = Build::event();
        $machine = $this->publication();
        $event->transitionPublication($machine, 'publish', [], null, []);

        $event->transitionPublication($machine, 'cancel', [], 'Bouřka', []);

        self::assertSame(PublicationState::Cancelled, $event->publicationState());
        self::assertSame('Bouřka', $event->cancellationReason());
        $this->expectException(TransitionNotAllowed::class);
        $event->transitionPublication($machine, 'publish', [], null, []);
    }

    public function test_an_illegal_transition_names_workflow_transition_and_state(): void
    {
        $event = Build::event();

        try {
            $event->transitionPublication($this->publication(), 'complete', [], null, []);
            self::fail('A draft cannot be completed.');
        } catch (TransitionNotAllowed $e) {
            self::assertSame(['workflow' => 'event_publication', 'transition' => 'complete', 'state' => 'draft'], $e->context());
        }
        self::assertSame(PublicationState::Draft, $event->publicationState(), 'a refused transition changes nothing');
    }

    public function test_a_guard_that_refuses_for_lack_of_permission_is_a_403_problem(): void
    {
        $this->dispatcher->addListener('workflow.event_publication.guard.publish', static function (GuardEvent $event): void {
            $event->addTransitionBlocker(new TransitionBlocker('no permission', Transitions::FORBIDDEN));
        });

        $this->expectException(TransitionForbidden::class);
        Build::event()->transitionPublication($this->publication(), 'publish', [], null, []);
    }

    public function test_another_blocker_is_reported_as_a_refused_transition_with_its_message(): void
    {
        $this->dispatcher->addListener('workflow.event_publication.guard.complete', static function (GuardEvent $event): void {
            $event->addTransitionBlocker(new TransitionBlocker('The event has not ended yet.', 'event_not_finished'));
        });
        $event = Build::event();
        $machine = $this->publication();
        $event->transitionPublication($machine, 'publish', [], null, []);

        try {
            $event->transitionPublication($machine, 'complete', [], null, []);
            self::fail('The guard blocks it.');
        } catch (TransitionNotAllowed $e) {
            self::assertStringContainsString('has not ended yet', $e->getMessage());
        }
    }

    public function test_the_registration_window_serves_events_and_activities_with_one_definition(): void
    {
        $event = Build::event();
        $activity = Build::activity($event);
        $machine = $this->registration();

        $event->transitionRegistration($machine, 'open', null, []);
        $activity->transitionRegistration($machine, 'open', null, []);
        $activity->transitionRegistration($machine, 'pause', 'Rozbité značení', []);
        $activity->transitionRegistration($machine, 'resume', null, []);
        $event->transitionRegistration($machine, 'close', null, []);

        self::assertSame(RegistrationState::Closed, $event->registrationState());
        self::assertSame(RegistrationState::Open, $activity->registrationState());

        $changes = array_values(array_filter($activity->releaseEvents(), static fn(object $e): bool => $e instanceof RegistrationWindowChanged));
        self::assertSame(['open', 'paused', 'open'], array_map(static fn(RegistrationWindowChanged $e): string => $e->to->value, $changes));
        self::assertSame($activity->id(), $changes[0]->activityId);
        self::assertSame('Rozbité značení', $changes[1]->reason);
    }

    public function test_a_closed_registration_cannot_be_reopened_and_not_open_cannot_pause(): void
    {
        $event = Build::event();
        $machine = $this->registration();

        try {
            $event->transitionRegistration($machine, 'pause', null, []);
            self::fail('Only an open registration can be paused.');
        } catch (TransitionNotAllowed) {
        }
        $event->transitionRegistration($machine, 'close', null, []);

        $this->expectException(TransitionNotAllowed::class);
        $event->transitionRegistration($machine, 'open', null, []);
    }

    private function publication(): StateMachine
    {
        $definition = (new DefinitionBuilder())
            ->addPlaces(['draft', 'published', 'completed', 'cancelled', 'archived'])
            ->setInitialPlaces('draft')
            ->addTransition(new Transition('publish', 'draft', 'published'))
            ->addTransition(new Transition('cancel', 'published', 'cancelled'))
            ->addTransition(new Transition('complete', 'published', 'completed'))
            ->addTransition(new Transition('archive', 'completed', 'archived'))
            ->addTransition(new Transition('archive', 'cancelled', 'archived'))
            ->build();

        return new StateMachine($definition, new MethodMarkingStore(true, 'publicationState'), $this->dispatcher, 'event_publication');
    }

    private function registration(): StateMachine
    {
        $builder = (new DefinitionBuilder())
            ->addPlaces(['not_open', 'open', 'paused', 'closed'])
            ->setInitialPlaces('not_open')
            ->addTransition(new Transition('open', 'not_open', 'open'))
            ->addTransition(new Transition('pause', 'open', 'paused'))
            ->addTransition(new Transition('resume', 'paused', 'open'));
        foreach (['not_open', 'open', 'paused'] as $from) {
            $builder->addTransition(new Transition('close', $from, 'closed'));
        }

        return new StateMachine($builder->build(), new MethodMarkingStore(true, 'registrationState'), $this->dispatcher, 'registration_window');
    }
}
