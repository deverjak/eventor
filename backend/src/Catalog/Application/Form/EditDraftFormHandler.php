<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Domain\Exception\InvalidFormSchema;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Form\FormVersion;
use Eventor\Catalog\Domain\Form\FormVersionRepository;
use Eventor\Catalog\Domain\Structure\ActivityRepository;
use Eventor\Catalog\Domain\Structure\EventRepository;
use Eventor\Catalog\Domain\Structure\VariantRepository;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\UserAccountId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Chapter 04, path 1 step 3 and path 9: saves the draft form. Editing a published form starts a draft of the next
 * version (the published one never changes, ADR 007). Fields bound to activities or variants must point to the ones
 * of this event.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class EditDraftFormHandler
{
    public function __construct(
        private EventRepository $events,
        private ActivityRepository $activities,
        private VariantRepository $variants,
        private FormVersionRepository $forms,
        private AuditContext $audit,
        private ClockInterface $clock,
    ) {}

    /**
     * @return array{formVersionId: string, number: int}
     *
     * @throws InvalidFormSchema
     */
    public function __invoke(EditDraftForm $command): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $event = $this->events->get($command->eventId);
        $schema = FormSchema::fromArray($command->schema);
        $this->assertReferences($schema, $event->id());

        $draft = $this->forms->draftOf($event->id());
        if (null === $draft) {
            $actor = $this->audit->actor();
            $draft = FormVersion::startDraft(
                FormVersionId::generate(),
                $event->organizerId(),
                $event->id(),
                $this->forms->highestNumber($event->id()) + 1,
                $schema,
                null !== $actor && null !== $actor->id && UserAccountId::isValid($actor->id) ? UserAccountId::fromString($actor->id) : null,
                $now,
            );
            $this->forms->add($draft);
        } else {
            $draft->replaceSchema($schema, $now);
        }

        return ['formVersionId' => $draft->id()->toString(), 'number' => $draft->number()];
    }

    private function assertReferences(FormSchema $schema, \Eventor\Shared\Domain\Id\EventId $event): void
    {
        $activityIds = array_map(static fn($a): string => $a->id()->toString(), $this->activities->forEvent($event));
        $variantIds = array_map(static fn($v): string => $v->id()->toString(), $this->variants->forEvent($event));

        $violations = [];
        foreach ($schema->fields as $index => $field) {
            $references = [
                'activityIds' => [$field->activityIds, $activityIds],
                'variantIds' => [$field->variantIds, $variantIds],
            ];
            if (null !== $field->condition && !$field->condition->dependsOnAnswers()) {
                $references['condition.ids'] = [$field->condition->ids, \Eventor\Catalog\Domain\Form\ConditionType::Activity === $field->condition->type ? $activityIds : $variantIds];
            }
            foreach ($references as $path => [$used, $known]) {
                foreach ($used as $id) {
                    if (!\in_array($id, $known, true)) {
                        $violations[] = ['path' => \sprintf('fields[%d].%s', $index, $path), 'message' => \sprintf('"%s" does not belong to this event.', $id), 'code' => 'invalid_reference'];
                    }
                }
            }
        }
        if ([] !== $violations) {
            throw new InvalidFormSchema($violations);
        }
    }
}
