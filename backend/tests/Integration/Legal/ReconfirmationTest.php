<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal;

use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\Event\DocumentVersionPublished;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Tests\Integration\Legal\Support\LegalTestCase;
use Eventor\Tests\Integration\Legal\Support\ReconfirmationSpy;
use Eventor\Tests\Support\Registration\InsertsParticipations;

/**
 * S14 and S15 (LEG-08): a new version without the flag changes nothing for old confirmations; with the flag everybody who
 * confirmed an older version is asked again. Nothing is ever granted automatically.
 */
final class ReconfirmationTest extends LegalTestCase
{
    use InsertsParticipations;

    public function test_a_version_without_the_flag_asks_nobody_s14(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId']);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), ParticipationId::generate());

        $this->publishVersion($organizer, $doc['documentId'], 'Cosmetic change');
        $this->drainQueues();

        self::assertSame([], ReconfirmationSpy::$received);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM legal.confirmation'));
    }

    public function test_a_version_with_the_flag_asks_everybody_who_confirmed_an_older_one_s15(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId']);
        $first = ParticipationId::generate();
        $second = ParticipationId::generate();
        $orderOnly = OrderId::generate();
        $third = ParticipationId::generate();
        foreach ([$first, $second, $third] as $id) {
            $this->insertParticipation($organizer, $id);
        }
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), $first);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), $second);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), order: $orderOnly);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), $third);
        $second2 = $this->publishVersion($organizer, $doc['documentId'], 'Material change', reconfirm: true);

        $this->drainQueues();

        $published = $this->published('legal_events', DocumentVersionPublished::class);
        self::assertTrue($published[1]->requiresReconfirmation);
        self::assertSame($doc['versionId'], $published[1]->previousVersionId?->toString());
        self::assertCount(1, ReconfirmationSpy::$received);
        $request = ReconfirmationSpy::$received[0];
        self::assertSame($requirement->toString(), $request->requirementId->toString());
        self::assertSame($second2, $request->versionId->toString());
        $participations = array_map(static fn(ParticipationId $id): string => $id->toString(), $request->participationIds);
        sort($participations);
        self::assertCount(3, $participations);
        self::assertContains($first->toString(), $participations);
        self::assertContains($second->toString(), $participations);
        self::assertContains($third->toString(), $participations);
        self::assertSame([$orderOnly->toString()], array_map(static fn(OrderId $id): string => $id->toString(), $request->orderIds));
        self::assertSame(4, $this->dbInt('SELECT count(*) FROM legal.confirmation'), 'silence is not consent: no confirmation was added');
    }

    public function test_those_who_withdrew_or_already_confirmed_the_new_version_are_not_asked(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $requirement = $this->requirement($organizer, $doc['documentId'], RequirementType::Likeness, mandatory: false);
        $withdrawer = ParticipationId::generate();
        $asked = ParticipationId::generate();
        $this->insertParticipation($organizer, $asked);
        $this->insertParticipation($organizer, $withdrawer);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, $person = PersonId::generate(), $withdrawer, OrderId::generate());
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Withdrawn, $person, $withdrawer);
        $this->decide($organizer, $requirement, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), $asked);

        $this->publishVersion($organizer, $doc['documentId'], 'Material change', reconfirm: true);
        $this->drainQueues();

        self::assertCount(1, ReconfirmationSpy::$received);
        self::assertSame([$asked->toString()], array_map(static fn(ParticipationId $id): string => $id->toString(), ReconfirmationSpy::$received[0]->participationIds));
    }

    public function test_nothing_is_announced_when_nobody_confirmed_the_older_version(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $this->requirement($organizer, $doc['documentId']);

        $this->publishVersion($organizer, $doc['documentId'], 'Material change', reconfirm: true);
        $this->drainQueues();

        self::assertSame([], ReconfirmationSpy::$received);
    }

    public function test_one_request_per_requirement_of_the_document(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $terms = $this->requirement($organizer, $doc['documentId'], purpose: 'terms');
        $health = $this->requirement($organizer, $doc['documentId'], RequirementType::HealthDeclaration, purpose: 'health');
        $participation = ParticipationId::generate();
        $this->insertParticipation($organizer, $participation);
        $person = PersonId::generate();
        $this->decide($organizer, $terms, $doc['versionId'], ConsentDecision::Granted, $person, $participation);
        $this->decide($organizer, $health, $doc['versionId'], ConsentDecision::Granted, $person, $participation);

        $this->publishVersion($organizer, $doc['documentId'], 'Material change', reconfirm: true);
        $this->drainQueues();

        $requirements = array_map(static fn($e): string => $e->requirementId->toString(), ReconfirmationSpy::$received);
        sort($requirements);
        $expected = [$terms->toString(), $health->toString()];
        sort($expected);
        self::assertSame($expected, $requirements);
    }
}
