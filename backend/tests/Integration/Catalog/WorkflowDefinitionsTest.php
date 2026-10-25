<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Catalog;

use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Contract\RegistrationState;
use Eventor\Tests\Support\Catalog\Build;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * ADR 011: the configured state machines (services.php) allow exactly the transitions of chapter 05, tables
 * "Událost: zveřejnění" and "Registrace". Guards are not evaluated here (no actor): only the definition.
 */
final class WorkflowDefinitionsTest extends IntegrationTestCase
{
    /**
     * @return iterable<string, array{PublicationState, list<string>}>
     */
    public static function publication(): iterable
    {
        yield 'draft' => [PublicationState::Draft, ['publish']];
        yield 'published' => [PublicationState::Published, ['cancel', 'complete']];
        yield 'completed' => [PublicationState::Completed, ['archive']];
        yield 'cancelled' => [PublicationState::Cancelled, ['archive']];
        yield 'archived' => [PublicationState::Archived, []];
    }

    /**
     * @param list<string> $allowed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('publication')]
    public function test_event_publication_transitions(PublicationState $state, array $allowed): void
    {
        $event = Build::event();
        $event->setPublicationState($state);

        self::assertSame($allowed, $this->names($this->machine('event_publication'), $event));
    }

    /**
     * @return iterable<string, array{RegistrationState, list<string>}>
     */
    public static function registration(): iterable
    {
        yield 'not open' => [RegistrationState::NotOpen, ['open', 'close']];
        yield 'open' => [RegistrationState::Open, ['pause', 'close']];
        yield 'paused' => [RegistrationState::Paused, ['resume', 'close']];
        yield 'closed' => [RegistrationState::Closed, []];
    }

    /**
     * @param list<string> $allowed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('registration')]
    public function test_registration_window_transitions_of_events_and_activities(RegistrationState $state, array $allowed): void
    {
        $event = Build::event();
        $event->setRegistrationState($state);
        $activity = Build::activity($event);
        $activity->setRegistrationState($state);
        $machine = $this->machine('registration_window');

        self::assertSame($allowed, $this->names($machine, $event));
        self::assertSame($allowed, $this->names($machine, $activity));
    }

    public function test_the_machines_start_where_the_database_defaults_start(): void
    {
        $event = Build::event();

        self::assertSame(PublicationState::Draft, $event->publicationState());
        self::assertSame(RegistrationState::NotOpen, $event->registrationState());
        self::assertSame(RegistrationState::NotOpen, Build::activity($event)->registrationState());
    }

    private function machine(string $name): WorkflowInterface
    {
        $machine = static::getContainer()->get('test.workflow.'.$name);
        \assert($machine instanceof WorkflowInterface);

        return $machine;
    }

    /**
     * @return list<string>
     */
    private function names(WorkflowInterface $machine, object $subject): array
    {
        $state = array_key_first($machine->getMarking($subject)->getPlaces());
        $names = [];
        foreach ($machine->getDefinition()->getTransitions() as $transition) {
            if (\in_array($state, $transition->getFroms(), true)) {
                $names[] = $transition->getName();
            }
        }

        return $names;
    }
}
