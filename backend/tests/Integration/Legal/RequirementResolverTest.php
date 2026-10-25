<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal;

use Eventor\Legal\Application\ArchiveRequirement\ArchiveRequirement;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\Exception\RequirementNotSatisfied;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Legal\Contract\SubmittedDecision;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Tests\Integration\Legal\Support\LegalTestCase;

/**
 * Which requirements apply (LEG-02, LEG-05, LEG-11, ORG-06, S13): inheritance from the organizer, additions and
 * replacements on lower levels, restrictions to an activity or variant, orderer versus participant versus guardian.
 */
final class RequirementResolverTest extends LegalTestCase
{
    public function test_the_organizer_defaults_apply_to_every_event_of_the_organizer(): void
    {
        $organizer = $this->organizer();
        $terms = $this->publishedDocument($organizer);
        $this->requirement($organizer, $terms['documentId'], confirmer: Confirmer::Orderer);
        $slot = $this->slot('a');

        $forEventOne = $this->resolver()->forRegistration($organizer, EventId::generate(), [$slot]);
        $forEventTwo = $this->resolver()->forRegistration($organizer, EventId::generate(), [$slot]);

        self::assertCount(1, $forEventOne->orderLevel);
        self::assertCount(1, $forEventTwo->orderLevel);
        self::assertSame($terms['versionId'], $forEventOne->orderLevel[0]->versionId->toString());
        self::assertSame(['a' => []], $forEventOne->perSlot);
    }

    public function test_another_organizer_sees_nothing_of_it(): void
    {
        $alpha = $this->organizer('alpha');
        $beta = $this->organizer('beta');
        $this->requirement($alpha, $this->publishedDocument($alpha)['documentId']);

        $applicable = $this->resolver()->forRegistration($beta, EventId::generate(), [$this->slot('a')]);

        self::assertSame([], $applicable->orderLevel);
        self::assertSame(['a' => []], $applicable->perSlot);
    }

    public function test_event_and_activity_documents_add_to_the_defaults_only_where_they_belong(): void
    {
        $organizer = $this->organizer();
        $event = EventId::generate();
        $activity = ActivityId::generate();
        $otherActivity = ActivityId::generate();
        $this->requirement($organizer, $this->publishedDocument($organizer)['documentId'], purpose: 'org');
        $this->requirement($organizer, $this->publishedDocument($organizer, DocumentLevel::Event, $event->toString(), title: 'Event')['documentId'], purpose: 'event');
        $this->requirement($organizer, $this->publishedDocument($organizer, DocumentLevel::Activity, $activity->toString(), title: 'Activity')['documentId'], RequirementType::HealthDeclaration, purpose: 'activity');

        $applicable = $this->resolver()->forRegistration($organizer, $event, [
            new RegistrationSlot('swimmer', $activity, VariantId::generate()),
            new RegistrationSlot('walker', $otherActivity, VariantId::generate()),
        ]);

        self::assertSame(['org', 'event', 'activity'], array_map(static fn($r): string => $r->purpose, $applicable->perSlot['swimmer']));
        self::assertSame(['org', 'event'], array_map(static fn($r): string => $r->purpose, $applicable->perSlot['walker']));

        $otherEvent = $this->resolver()->forRegistration($organizer, EventId::generate(), [new RegistrationSlot('x', $activity, VariantId::generate())]);
        self::assertSame(['org', 'activity'], array_map(static fn($r): string => $r->purpose, $otherEvent->perSlot['x']), 'event documents stay with their event');
    }

    public function test_a_lower_level_document_can_replace_a_higher_one(): void
    {
        $organizer = $this->organizer();
        $event = EventId::generate();
        $general = $this->publishedDocument($organizer, title: 'General terms');
        $this->requirement($organizer, $general['documentId'], purpose: 'general');
        $special = $this->publishedDocument($organizer, DocumentLevel::Event, $event->toString(), title: 'Camp terms', replaces: $general['documentId']);
        $this->requirement($organizer, $special['documentId'], purpose: 'camp');
        $slot = $this->slot('a');

        $replaced = $this->resolver()->forRegistration($organizer, $event, [$slot]);
        $elsewhere = $this->resolver()->forRegistration($organizer, EventId::generate(), [$slot]);

        self::assertSame(['camp'], array_map(static fn($r): string => $r->purpose, $replaced->perSlot['a']));
        self::assertSame(['general'], array_map(static fn($r): string => $r->purpose, $elsewhere->perSlot['a']));
    }

    public function test_a_requirement_can_be_restricted_to_an_activity_and_a_variant(): void
    {
        $organizer = $this->organizer();
        $event = EventId::generate();
        $activity = ActivityId::generate();
        $variant = VariantId::generate();
        $doc = $this->publishedDocument($organizer, DocumentLevel::Event, $event->toString());
        $this->requirement($organizer, $doc['documentId'], RequirementType::HealthDeclaration, activityId: $activity->toString(), variantId: $variant->toString());

        $applicable = $this->resolver()->forRegistration($organizer, $event, [
            new RegistrationSlot('match', $activity, $variant),
            new RegistrationSlot('wrong-variant', $activity, VariantId::generate()),
            new RegistrationSlot('wrong-activity', ActivityId::generate(), $variant),
        ]);

        self::assertCount(1, $applicable->perSlot['match']);
        self::assertSame([], $applicable->perSlot['wrong-variant']);
        self::assertSame([], $applicable->perSlot['wrong-activity']);
    }

    public function test_guardian_requirements_apply_only_to_children_s13(): void
    {
        $organizer = $this->organizer();
        $event = EventId::generate();
        $doc = $this->publishedDocument($organizer);
        $guardian = $this->requirement($organizer, $doc['documentId'], RequirementType::Terms, confirmer: Confirmer::Guardian, purpose: 'guardian consent');
        $this->requirement($organizer, $doc['documentId'], RequirementType::PrivacyNotice, confirmer: Confirmer::Orderer, purpose: 'privacy');
        $activity = ActivityId::generate();
        $variant = VariantId::generate();

        $applicable = $this->resolver()->forRegistration($organizer, $event, [
            new RegistrationSlot('parent', $activity, $variant, minor: false),
            new RegistrationSlot('child-1', $activity, $variant, minor: true),
            new RegistrationSlot('child-2', $activity, $variant, minor: true),
        ]);

        self::assertSame([], $applicable->perSlot['parent']);
        self::assertSame($guardian->toString(), $applicable->perSlot['child-1'][0]->requirementId->toString());
        self::assertSame($guardian->toString(), $applicable->perSlot['child-2'][0]->requirementId->toString());
        self::assertCount(1, $applicable->orderLevel, 'the orderer confirms the privacy notice once for the whole order');
    }

    public function test_a_document_without_a_published_version_and_an_archived_requirement_do_not_apply(): void
    {
        $organizer = $this->organizer();
        $event = EventId::generate();
        $doc = $this->publishedDocument($organizer);
        $archived = $this->requirement($organizer, $doc['documentId']);
        $this->dispatch($organizer, new ArchiveRequirement($archived->toString()));
        /** @var array{documentId: string} $draftOnly */
        $draftOnly = $this->dispatch($organizer, new \Eventor\Legal\Application\CreateDocument\CreateLegalDocument(DocumentLevel::Event, $event->toString(), 'Draft only'));
        $this->requirement($organizer, $draftOnly['documentId']);

        $applicable = $this->resolver()->forRegistration($organizer, $event, [$this->slot('a')]);

        self::assertSame(['a' => []], $applicable->perSlot);
    }

    public function test_the_current_version_is_used_after_a_new_one_is_published(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $this->requirement($organizer, $doc['documentId']);
        $second = $this->publishVersion($organizer, $doc['documentId'], 'Second version');

        $applicable = $this->resolver()->forRegistration($organizer, EventId::generate(), [$this->slot('a')]);

        self::assertSame($second, $applicable->perSlot['a'][0]->versionId->toString());
    }

    public function test_assert_satisfied_demands_a_granted_decision_for_the_current_version_of_every_mandatory_requirement(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $terms = $this->requirement($organizer, $doc['documentId'], purpose: 'terms');
        $photos = $this->requirement($organizer, $doc['documentId'], RequirementType::Likeness, mandatory: false, purpose: 'photos');
        $applicable = $this->resolver()->forRegistration($organizer, EventId::generate(), [$this->slot('a')]);
        $version = DocumentVersionId::fromString($doc['versionId']);

        // The voluntary photo consent may stay undecided or declined.
        $this->resolver()->assertSatisfied($applicable, [new SubmittedDecision($terms, $version, 'a', ConsentDecision::Granted), new SubmittedDecision($photos, $version, 'a', ConsentDecision::Declined)]);

        foreach ([
            [],
            [new SubmittedDecision($terms, $version, 'a', ConsentDecision::Declined)],
            [new SubmittedDecision($terms, $version, 'other-slot', ConsentDecision::Granted)],
            [new SubmittedDecision($terms, $version, null, ConsentDecision::Granted)],
            [new SubmittedDecision($terms, DocumentVersionId::generate(), 'a', ConsentDecision::Granted)],
        ] as $decisions) {
            try {
                $this->resolver()->assertSatisfied($applicable, $decisions);
                self::fail('Expected the decisions to be insufficient.');
            } catch (RequirementNotSatisfied $e) {
                self::assertSame('confirmations.'.$terms->toString(), $e->violations()[0]['path']);
            }
        }
    }

    private function slot(string $ref): RegistrationSlot
    {
        return new RegistrationSlot($ref, ActivityId::generate(), VariantId::generate());
    }

    private function resolver(): RequirementResolver
    {
        return $this->service(RequirementResolver::class);
    }
}
