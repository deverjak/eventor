<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publication;

use Eventor\Catalog\Application\Publish\EventPublishabilityChecker;
use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Contract\PublicationState;
use Eventor\Catalog\Domain\Exception\TransitionNotAllowed;
use Eventor\Catalog\Domain\Form\DefaultFormSchema;
use Eventor\Catalog\Domain\Form\FormVersion;
use Eventor\Catalog\Domain\Form\FormVersionRepository;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Eventor\Registration\Contract\CapacityBucketSpec;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Shared\Domain\Capacity\CapacityScope;
use Eventor\Shared\Domain\Capacity\CapacityUnit;
use Eventor\Shared\Domain\Id\FormVersionId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Chapter 04, path 1, step 5 - one transaction: the publication rules, the immutable `FormVersion`, the capacity
 * buckets of the event, its activities and variants (created by Registration through the facade, in this very
 * transaction), the `publish` transition. The outbox then carries `catalog.event_published` and
 * `catalog.form_version_published`.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishEventHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
        private FormVersionRepository $forms,
        private EventPublishabilityChecker $checker,
        private CapacityProvisioner $capacity,
        private WorkflowInterface $eventPublicationStateMachine,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{formVersionId: string, formVersion: int}
     */
    public function __invoke(PublishEvent $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $event = $this->events->get($command->eventId);
        if (PublicationState::Draft !== $event->publicationState()) {
            throw new TransitionNotAllowed('event_publication', 'publish', $event->publicationState()->value, 'Only a draft event can be published.');
        }

        $activities = $this->activities->forEvent($event->id());
        $variants = $this->variants->forEvent($event->id());
        $draft = $this->forms->draftOf($event->id()) ?? $this->startDraft($event);
        $this->checker->assertPublishable(new PublishCandidate($event, $activities, $variants, $draft->schema()));

        $specs = [new CapacityBucketSpec($event->capacityBucketId(), CapacityScope::Event, $event->id()->toString(), $event->capacityLimit(), CapacityUnit::Seat)];
        foreach ($activities as $activity) {
            $specs[] = new CapacityBucketSpec($activity->capacityBucketId(), CapacityScope::Activity, $activity->id()->toString(), $activity->capacityLimit(), CapacityUnit::Seat);
        }
        foreach ($variants as $variant) {
            $specs[] = new CapacityBucketSpec($variant->capacityBucketId(), CapacityScope::Variant, $variant->id()->toString(), $variant->capacityLimit(), $variant->capacityUnit());
        }
        $this->capacity->provision($event->organizerId(), $event->id(), $specs);

        $event->transitionPublication(
            $this->eventPublicationStateMachine,
            'publish',
            array_map(static fn(Activity $activity) => $activity->id(), $activities),
            null,
            [],
        );
        $draft->publish($this->forms->latestPublished($event->id()), $now);

        return ['formVersionId' => $draft->id()->toString(), 'formVersion' => $draft->number()];
    }

    private function startDraft(\Eventor\Catalog\Domain\Structure\Event $event): FormVersion
    {
        $draft = FormVersion::startDraft(
            FormVersionId::generate(),
            $event->organizerId(),
            $event->id(),
            $this->forms->highestNumber($event->id()) + 1,
            DefaultFormSchema::create(),
            null,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
        $this->forms->add($draft);

        return $draft;
    }
}
