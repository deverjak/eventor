<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Application\Publish\EventPublishabilityChecker;
use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Domain\Exception\InvalidCatalogData;
use Eventor\Catalog\Domain\Form\FormVersionRepository;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * FRM-08, FRM-09 (chapter 04, path 9): freezes the draft as the next immutable version. New registrations are
 * validated against it, old answers keep their version; `catalog.form_version_published` lists the required fields
 * that are new, so Registration can ask for them (S17). The first version is published with the event.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishFormVersionHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
        private FormVersionRepository $forms,
        private EventPublishabilityChecker $checker,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{formVersionId: string, number: int}
     */
    public function __invoke(PublishFormVersion $command): array
    {
        $event = $this->events->get($command->eventId);
        if ($event->isDraft()) {
            throw InvalidCatalogData::because('The first form version is published together with the event.');
        }
        $draft = $this->forms->draftOf($event->id()) ?? throw InvalidCatalogData::because('There is no draft of the form to publish; edit the form first.');

        $this->checker->assertPublishable(new PublishCandidate($event, $this->activities->forEvent($event->id()), $this->variants->forEvent($event->id()), $draft->schema()));
        $draft->publish($this->forms->latestPublished($event->id()), \DateTimeImmutable::createFromInterface($this->clock->now()));

        return ['formVersionId' => $draft->id()->toString(), 'number' => $draft->number()];
    }
}
