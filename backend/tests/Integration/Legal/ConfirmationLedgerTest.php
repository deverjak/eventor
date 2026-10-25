<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal;

use Doctrine\DBAL\Exception as DbalException;
use Eventor\Legal\Contract\ConfirmationChannel;
use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Exception\ConfirmationNotAllowed;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Domain\Id\DocumentVersionId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Tests\Integration\Legal\Support\LegalTestCase;
use Eventor\Tests\Integration\Legal\Support\RecordConfirmationsCommand;
use Eventor\Tests\Integration\Legal\Support\RecordedSpy;
use Eventor\Tests\Integration\Legal\Support\WithdrawnSpy;

/**
 * The append-only confirmation ledger (LEG-07, LEG-09, LEG-11, ADR 012): rows chain to their predecessor, rules per
 * decision, integration events, S16 withdrawal that keeps the registration, and the database grants.
 */
final class ConfirmationLedgerTest extends LegalTestCase
{
    public function test_a_granted_decision_is_appended_audited_and_announced(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId']);
        $person = PersonId::generate();
        $participation = ParticipationId::generate();

        [$id] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $person, $participation);

        $row = $this->dbRow('SELECT requirement_id, document_version_id, person_id, participation_id, decision, channel, supersedes_id FROM legal.confirmation WHERE id = :id', ['id' => $id]);
        self::assertSame([$requirement->toString(), $doc['versionId'], $person->toString(), $participation->toString(), 'granted', 'web', null], array_values($row));
        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE action = 'legal.confirmation_granted' AND entity_id = :id", ['id' => $id]));
        $this->drainQueues();
        self::assertCount(1, RecordedSpy::$received);
        self::assertSame($id, RecordedSpy::$received[0]->confirmationId->toString());
        self::assertSame(ConsentDecision::Granted, RecordedSpy::$received[0]->decision);
        self::assertSame([], WithdrawnSpy::$received);
    }

    public function test_a_later_decision_is_a_new_row_that_supersedes_the_previous_one(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::Consent, mandatory: false);
        $person = PersonId::generate();
        $participation = ParticipationId::generate();

        [$granted] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $person, $participation);
        [$declined] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Declined, $person, $participation);
        [$again] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $person, $participation);

        self::assertSame(3, $this->dbInt('SELECT count(*) FROM legal.confirmation WHERE requirement_id = :r', ['r' => $requirement->toString()]));
        self::assertSame($granted, $this->dbString('SELECT supersedes_id FROM legal.confirmation WHERE id = :id', ['id' => $declined]));
        self::assertSame($declined, $this->dbString('SELECT supersedes_id FROM legal.confirmation WHERE id = :id', ['id' => $again]));
    }

    public function test_two_decisions_for_the_same_chain_in_one_call_are_chained(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::Consent, mandatory: false);
        $person = PersonId::generate();
        $participation = ParticipationId::generate();
        $version = DocumentVersionId::fromString($doc['versionId']);
        $at = new \DateTimeImmutable('2026-11-01T10:00:00+00:00');

        /** @var list<string> $ids */
        $ids = $this->dispatch($organizer, new RecordConfirmationsCommand($organizer, [
            new ConfirmationToRecord($requirement, $version, ConsentDecision::Granted, $person, null, $participation, null, ConfirmationChannel::Web, null, $at),
            new ConfirmationToRecord($requirement, $version, ConsentDecision::Withdrawn, $person, null, $participation, OrderId::generate(), ConfirmationChannel::Web, null, $at),
        ]));

        self::assertSame($ids[0], $this->dbString('SELECT supersedes_id FROM legal.confirmation WHERE id = :id', ['id' => $ids[1]]));
    }

    public function test_a_mandatory_requirement_cannot_be_declined(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId']);

        $this->expectException(ConfirmationNotAllowed::class);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Declined, PersonId::generate());
    }

    public function test_a_non_revocable_confirmation_cannot_be_withdrawn(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId']);
        $person = PersonId::generate();
        $participation = ParticipationId::generate();
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $person, $participation);

        $this->expectException(ConfirmationNotAllowed::class);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Withdrawn, $person, $participation, OrderId::generate());
    }

    public function test_only_a_granted_confirmation_can_be_withdrawn(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::Likeness, mandatory: false);

        $this->expectException(ConfirmationNotAllowed::class);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Withdrawn, PersonId::generate(), ParticipationId::generate(), OrderId::generate());
    }

    public function test_withdrawing_a_consent_announces_it_and_leaves_the_grant_in_the_history_s16(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::Likeness, mandatory: false);
        $person = PersonId::generate();
        $participation = ParticipationId::generate();
        $order = OrderId::generate();
        [$granted] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $person, $participation, $order);

        [$withdrawn] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Withdrawn, $person, $participation);

        self::assertSame(['granted', 'withdrawn'], $this->dbColumn('SELECT decision FROM legal.confirmation WHERE requirement_id = :r ORDER BY decided_at, id', ['r' => $requirement->toString()]));
        self::assertSame($granted, $this->dbString('SELECT supersedes_id FROM legal.confirmation WHERE id = :id', ['id' => $withdrawn]));
        $this->drainQueues();
        self::assertCount(1, WithdrawnSpy::$received);
        self::assertSame(RequirementType::Likeness, WithdrawnSpy::$received[0]->requirementType);
        self::assertSame($order->toString(), WithdrawnSpy::$received[0]->orderId->toString(), 'the order is taken from the withdrawn grant');
        self::assertSame($participation->toString(), WithdrawnSpy::$received[0]->participationId?->toString());
        self::assertCount(2, RecordedSpy::$received);
    }

    public function test_a_guardian_decides_for_a_child_leg11(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::HealthDeclaration);
        $child = PersonId::generate();
        $guardian = PersonId::generate();

        [$id] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $child, ParticipationId::generate(), guardian: $guardian);

        $row = $this->dbRow('SELECT person_id, decided_by_person_id FROM legal.confirmation WHERE id = :id', ['id' => $id]);
        self::assertSame([$child->toString(), $guardian->toString()], array_values($row));
    }

    public function test_a_staff_decision_is_stored_with_its_note(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::HealthDeclaration);

        [$id] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), ParticipationId::generate(), channel: ConfirmationChannel::Staff, note: 'signed on paper at the desk');

        self::assertSame(['staff', 'signed on paper at the desk'], array_values($this->dbRow('SELECT channel, note FROM legal.confirmation WHERE id = :id', ['id' => $id])));
    }

    public function test_the_version_must_be_a_published_version_of_the_requirements_document(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $other = $this->publishedDocument($organizer, title: 'Other');
        $requirement = $this->requirement($organizer, $doc['documentId']);

        $this->expectException(ConfirmationNotAllowed::class);
        $this->decide($organizer, $requirement, $other['versionId'], ConsentDecision::Granted, PersonId::generate());
    }

    public function test_a_requirement_of_another_organizer_cannot_be_confirmed(): void
    {
        $alpha = $this->organizer('alpha');
        $beta = $this->organizer('beta');
        $doc = $this->publishedDocument($alpha);
        $requirement = $this->requirement($alpha, $doc['documentId']);

        $this->expectException(ConfirmationNotAllowed::class);
        $this->decide($beta, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate());
    }

    public function test_the_application_role_cannot_update_or_delete_a_confirmation(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId']);
        [$id] = $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate());

        $this->expectException(DbalException::class);
        $this->connection()->executeStatement("UPDATE legal.confirmation SET decision = 'declined' WHERE id = :id", ['id' => $id]);
    }
}
