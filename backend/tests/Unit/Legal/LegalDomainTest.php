<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Legal;

use Doctrine\DBAL\Connection;
use Eventor\Legal\Application\Facade\DbalRequirementResolver;
use Eventor\Legal\Application\Validation\NoPrecheckedConsent;
use Eventor\Legal\Application\Validation\NoPrecheckedConsentValidator;
use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\ApplicableRequirements;
use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\Confirmer;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\DocumentLevel;
use Eventor\Legal\Contract\Exception\ConfirmationNotAllowed;
use Eventor\Legal\Contract\Exception\RequirementNotSatisfied;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Legal\Contract\SubmittedDecision;
use Eventor\Legal\Domain\Confirmation\Confirmation;
use Eventor\Legal\Domain\Document\CanonicalContent;
use Eventor\Legal\Domain\Document\DocumentVersion;
use Eventor\Legal\Domain\Document\LegalDocument;
use Eventor\Legal\Domain\Document\SourceKind;
use Eventor\Legal\Domain\Event\ConfirmationAppended;
use Eventor\Legal\Domain\Event\VersionPublished;
use Eventor\Legal\Domain\Exception\DocumentVersionAlreadyPublished;
use Eventor\Legal\Domain\Exception\InvalidDocument;
use Eventor\Legal\Domain\Exception\InvalidRequirement;
use Eventor\Legal\Domain\Requirement\LegalRequirement;
use Eventor\Legal\Domain\Requirement\RequirementPolicy;
use Eventor\Shared\Domain\Id\ConfirmationId;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\LegalDocumentId;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Domain rules of Legal without a kernel: requirement policy (LEG-06), canonical content and hashes (ADR 012),
 * the document / version life cycle, the ledger rules and the pure part of the resolver.
 */
final class LegalDomainTest extends TestCase
{
    private const string NOW = '2026-11-01T10:00:00+00:00';

    public function test_a_voluntary_consent_is_never_mandatory_prechecked_or_irrevocable(): void
    {
        foreach ([RequirementType::Consent, RequirementType::Likeness] as $type) {
            self::assertCount(3, RequirementPolicy::violations($type, true, false, true));
            self::assertSame([], RequirementPolicy::violations($type, false, true, false));
        }
        foreach ([RequirementType::Terms, RequirementType::PrivacyNotice, RequirementType::HealthDeclaration] as $type) {
            self::assertSame([], RequirementPolicy::violations($type, true, false, false));
        }
    }

    public function test_the_requirement_aggregate_enforces_the_policy_and_records_an_event(): void
    {
        $organizer = OrganizerId::generate();
        $requirement = LegalRequirement::define(LegalRequirementId::generate(), $organizer, LegalDocumentId::generate(), RequirementType::Terms, ' Participation ', null, null, Confirmer::Orderer, false, true, false, null, null, $this->now());

        self::assertSame('Participation', $requirement->purpose());
        self::assertCount(1, $requirement->releaseEvents());

        $this->expectException(InvalidRequirement::class);
        LegalRequirement::define(LegalRequirementId::generate(), $organizer, LegalDocumentId::generate(), RequirementType::Consent, 'News', null, null, Confirmer::EachParticipant, true, true, false, null, null, $this->now());
    }

    public function test_the_constraint_reports_each_violation_with_its_field(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $command = new \Eventor\Legal\Application\DefineRequirement\DefineRequirement(LegalDocumentId::generate()->toString(), RequirementType::Consent, 'News', mandatory: true, preChecked: true);

        $violations = $validator->validate($command);

        $paths = array_map(static fn($v): string => $v->getPropertyPath(), iterator_to_array($violations));
        sort($paths);
        self::assertSame(['mandatory', 'preChecked'], $paths);
        self::assertSame(NoPrecheckedConsentValidator::class, (new NoPrecheckedConsent())->validatedBy());
        self::assertCount(0, $validator->validate(new \Eventor\Legal\Application\DefineRequirement\DefineRequirement(LegalDocumentId::generate()->toString(), RequirementType::Consent, 'News')));
    }

    public function test_text_is_normalised_so_equal_text_has_equal_hash_and_key(): void
    {
        $composed = CanonicalContent::text("Caf\u{00E9}\r\nline");
        $decomposed = CanonicalContent::text("Cafe\u{0301}\nline");

        self::assertSame($composed->sha256, $decomposed->sha256);
        self::assertSame($composed->storageKey(), $decomposed->storageKey());
        self::assertSame(hash('sha256', "Caf\u{00E9}\nline"), $composed->sha256);
        self::assertSame(\sprintf('sha256/%s/%s.md', substr($composed->sha256, 0, 2), $composed->sha256), $composed->storageKey());
        self::assertSame(SourceKind::Text, $composed->kind);
    }

    public function test_content_is_validated(): void
    {
        self::assertSame('pdf', CanonicalContent::pdf('%PDF-1.7 body')->extension);
        self::assertSame('html', CanonicalContent::externalSnapshot('https://a.example/x', '<p>x</p>', 'text/html; charset=utf-8', $this->now())->extension);
        self::assertSame(SourceKind::ExternalSnapshot, CanonicalContent::externalSnapshot('https://a.example/x', '%PDF-1.4 x', 'application/pdf', $this->now())->kind);

        foreach ([
            static fn() => CanonicalContent::text("  \n"),
            static fn() => CanonicalContent::text("\xC3\x28"),
            static fn() => CanonicalContent::pdf('GIF89a'),
            static fn() => CanonicalContent::externalSnapshot('https://a.example/x', 'x', 'application/zip', new \DateTimeImmutable()),
        ] as $make) {
            try {
                $make();
                self::fail('Expected InvalidDocument.');
            } catch (InvalidDocument) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_publishing_moves_the_current_pointer_and_records_the_event_once(): void
    {
        $organizer = OrganizerId::generate();
        $document = LegalDocument::create(LegalDocumentId::generate(), $organizer, DocumentLevel::Organizer, $organizer->toString(), 'Terms', null, $this->now());
        $first = $this->draft($document, 1);
        $second = $this->draft($document, 2);

        $document->publish($first, true, $this->now(), 'user-1');
        $document->publish($second, true, $this->now(), 'user-1');

        $events = array_values(array_filter($document->releaseEvents(), static fn(object $e): bool => $e instanceof VersionPublished));
        self::assertCount(2, $events);
        self::assertNull($events[0]->previousVersionId);
        self::assertFalse($events[0]->requiresReconfirmation, 'nothing to reconfirm on the first version');
        self::assertSame($first->id()->toString(), $events[1]->previousVersionId?->toString());
        self::assertTrue($events[1]->requiresReconfirmation);
        self::assertSame($second->id()->toString(), $document->currentVersionId()?->toString());

        $this->expectException(DocumentVersionAlreadyPublished::class);
        $document->publish($second, false, $this->now(), null);
    }

    public function test_a_replacing_document_must_be_of_a_lower_level(): void
    {
        $organizer = OrganizerId::generate();
        $general = LegalDocument::create(LegalDocumentId::generate(), $organizer, DocumentLevel::Organizer, $organizer->toString(), 'General', null, $this->now());
        $event = LegalDocument::create(LegalDocumentId::generate(), $organizer, DocumentLevel::Event, 'a0000000-0000-7000-8000-000000000001', 'Event', $general, $this->now());

        self::assertSame($general->id()->toString(), $event->replacesDocumentId()?->toString());
        $this->expectException(InvalidDocument::class);
        LegalDocument::create(LegalDocumentId::generate(), $organizer, DocumentLevel::Organizer, $organizer->toString(), 'Upwards', $event, $this->now());
    }

    public function test_ledger_rules_per_decision(): void
    {
        $mandatory = $this->requirement(RequirementType::Terms, mandatory: true, revocable: false);
        $voluntary = $this->requirement(RequirementType::Likeness, mandatory: false, revocable: true);

        self::assertSame(ConsentDecision::Granted, $this->append($mandatory, ConsentDecision::Granted)->decision());
        self::assertSame(ConsentDecision::Declined, $this->append($voluntary, ConsentDecision::Declined)->decision());
        self::assertSame(ConsentDecision::Withdrawn, $this->append($voluntary, ConsentDecision::Withdrawn, ConsentDecision::Granted)->decision());

        foreach ([
            [$mandatory, ConsentDecision::Declined, null],
            [$mandatory, ConsentDecision::Withdrawn, ConsentDecision::Granted],
            [$voluntary, ConsentDecision::Withdrawn, null],
            [$voluntary, ConsentDecision::Withdrawn, ConsentDecision::Declined],
        ] as [$requirement, $decision, $previous]) {
            try {
                $this->append($requirement, $decision, $previous);
                self::fail('Expected ConfirmationNotAllowed.');
            } catch (ConfirmationNotAllowed) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_ledger_row_records_an_event_without_personal_data(): void
    {
        $row = $this->append($this->requirement(RequirementType::Terms, true, false), ConsentDecision::Granted);

        $events = $row->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ConfirmationAppended::class, $events[0]);
        self::assertSame(['requirementId', 'versionId', 'decision', 'channel'], array_keys($events[0]->auditChange()->after ?? []));
    }

    public function test_assert_satisfied_checks_mandatory_requirements_against_the_current_version(): void
    {
        $terms = $this->applicable(true);
        $optional = $this->applicable(false);
        $resolver = new DbalRequirementResolver(self::createStub(Connection::class));
        $requirements = new ApplicableRequirements([], ['a' => [$terms, $optional], 'b' => [$terms]]);

        $resolver->assertSatisfied($requirements, [
            new SubmittedDecision($terms->requirementId, $terms->versionId, 'a', ConsentDecision::Granted),
            new SubmittedDecision($terms->requirementId, $terms->versionId, 'b', ConsentDecision::Granted),
        ]);

        try {
            $resolver->assertSatisfied($requirements, [new SubmittedDecision($terms->requirementId, $terms->versionId, 'a', ConsentDecision::Granted)]);
            self::fail('Slot b is missing its confirmation.');
        } catch (RequirementNotSatisfied $e) {
            self::assertSame([['path' => 'confirmations.'.$terms->requirementId->toString(), 'message' => 'Confirmation is required.', 'code' => 'missing_confirmation']], $e->violations());
        }
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    private function draft(LegalDocument $document, int $number): DocumentVersion
    {
        return DocumentVersion::draft(DocumentVersionId::generate(), $document->organizerId(), $document->id(), $number, SourceKind::Text, 'text/markdown', null, null, hash('sha256', (string) $number), 'sha256/xx/'.$number.'.md', $this->now());
    }

    private function requirement(RequirementType $type, bool $mandatory, bool $revocable): LegalRequirement
    {
        return LegalRequirement::define(LegalRequirementId::generate(), OrganizerId::generate(), LegalDocumentId::generate(), $type, 'Purpose', null, null, Confirmer::EachParticipant, $revocable, $mandatory, false, null, null, $this->now());
    }

    private function append(LegalRequirement $requirement, ConsentDecision $decision, ?ConsentDecision $previous = null): Confirmation
    {
        return Confirmation::append(ConfirmationId::generate(), $requirement, DocumentVersionId::generate(), $decision, PersonId::generate(), null, ParticipationId::generate(), null, ConfirmationChannel::Web, null, $this->now(), null === $previous ? null : ConfirmationId::generate(), $previous);
    }

    private function applicable(bool $mandatory): ApplicableRequirement
    {
        return new ApplicableRequirement(LegalRequirementId::generate(), LegalDocumentId::generate(), DocumentVersionId::generate(), RequirementType::Terms, 'Purpose', Confirmer::EachParticipant, $mandatory, false, 'Terms');
    }
}
