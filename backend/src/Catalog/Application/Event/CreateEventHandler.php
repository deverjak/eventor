<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Event;

use Eventor\Catalog\Domain\Exception\EventSlugTaken;
use Eventor\Catalog\Domain\Form\DefaultFormSchema;
use Eventor\Catalog\Domain\Form\FormVersion;
use Eventor\Catalog\Domain\Form\FormVersionRepository;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\ActivityDetails;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Catalog\Domain\Structure\Variant;
use Eventor\Catalog\Domain\Structure\VariantDetails;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Shared\Domain\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Chapter 04, path 1, step 1: a draft event with its draft form; the "simple" mode adds the implicit activity and
 * variant. No capacity bucket exists yet (they are created when the event is published).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateEventHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
        private FormVersionRepository $forms,
        private SluggerInterface $slugger,
        private ClockInterface $clock,
        private AuditContext $audit,
        private ?OrganizerSettings $settings = null,
    ) {}

    /**
     * @return array{eventId: string, slug: string, activityId: string|null, variantId: string|null}
     */
    public function __invoke(CreateEvent $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $input = $command->input;
        $details = $input->details->toDetails();

        $slug = null === $input->slug ? $this->uniqueSlug($details->name) : $input->slug;
        if ($this->events->slugExists($slug)) {
            throw EventSlugTaken::create($slug);
        }

        $event = Event::create(EventId::generate(), $command->organizerId, $slug, $details, $this->defaultPaymentRules($command), $now);
        $this->events->add($event);

        $activityId = null;
        $variantId = null;
        if ('simple' === $input->mode) {
            $activity = Activity::create(
                ActivityId::generate(),
                $command->organizerId,
                $event->id(),
                new ActivityDetails($details->name, null, null, $details->startsAt, $details->endsAt, null, null, null, null, false),
                true,
                0,
                $now,
            );
            $variant = Variant::create(
                VariantId::generate(),
                $command->organizerId,
                $event->id(),
                $activity->id(),
                new VariantDetails('Účast', $input->price ?? Money::zero(), null, null, null),
                0,
                $now,
                $input->capacityLimit,
            );
            $this->activities->add($activity);
            $this->variants->add($variant);
            $activityId = $activity->id()->toString();
            $variantId = $variant->id()->toString();
        }

        $actor = $this->audit->actor();
        $creator = null !== $actor && null !== $actor->id && UserAccountId::isValid($actor->id) ? UserAccountId::fromString($actor->id) : null;
        $this->forms->add(FormVersion::startDraft(FormVersionId::generate(), $command->organizerId, $event->id(), 1, DefaultFormSchema::create(), $creator, $now));

        return ['eventId' => $event->id()->toString(), 'slug' => $slug, 'activityId' => $activityId, 'variantId' => $variantId];
    }

    private function defaultPaymentRules(CreateEvent $command): PaymentRules
    {
        if (null === $this->settings) {
            return PaymentRules::defaults();
        }
        $policy = $this->settings->policy($command->organizerId);

        return PaymentRules::defaults($policy->defaultDueDays, $policy->defaultHoldTtlHours, $policy->autoRestoreLatePayment);
    }

    private function uniqueSlug(string $name): string
    {
        $base = strtolower($this->slugger->slug($name, '-', 'cs')->toString());
        $base = trim(substr($base, 0, 80), '-');
        $base = '' === $base ? 'event' : $base;
        $slug = $base;
        for ($i = 2; $this->events->slugExists($slug); ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
