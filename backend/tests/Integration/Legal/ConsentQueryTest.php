<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal;

use Eventor\Legal\Application\Facade\DbalConsentQuery;
use Eventor\Legal\Contract\ConsentDecision;
use Eventor\Legal\Contract\ConsentQuery;
use Eventor\Legal\Contract\MissingConfirmation;
use Eventor\Legal\Contract\RequirementResolver;
use Eventor\Legal\Contract\RequirementType;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Contract\Exception\ParticipationNotFound;
use Eventor\Registration\Contract\LegalState;
use Eventor\Registration\Contract\ParticipationCheckInView;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\ParticipationState;
use Eventor\Registration\Contract\ParticipationView;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\ParticipationId;
use Eventor\Shared\Domain\Id\PersonId;
use Eventor\Shared\Domain\Id\VariantId;
use Eventor\Tests\Integration\Legal\Support\LegalTestCase;

/**
 * Current state of confirmations (`ConsentQuery`): what a participation still owes, the marketing audience and the
 * persons with a likeness consent. Registration's `ParticipationQuery` is replaced by a stub that serves canned facts.
 */
final class ConsentQueryTest extends LegalTestCase
{
    public function test_a_participation_owes_every_mandatory_requirement_without_a_granted_decision(): void
    {
        [$organizer, $event, $view, $query] = $this->setUpParticipation();
        $doc = $this->publishedDocument($organizer);
        $terms = $this->requirement($organizer, $doc['documentId'], purpose: 'terms');
        $this->requirement($organizer, $doc['documentId'], RequirementType::Consent, mandatory: false, purpose: 'newsletter');

        $missing = $query->missingFor($organizer, [$view->id]);

        self::assertSame([$view->id->toString()], array_keys($missing));
        self::assertCount(1, $missing[$view->id->toString()], 'a voluntary consent is not owed');
        self::assertSame($terms->toString(), $missing[$view->id->toString()][0]->requirementId->toString());
        self::assertFalse($missing[$view->id->toString()][0]->reconfirmation);

        $this->decide($organizer, $terms, $doc['versionId'], ConsentDecision::Granted, $view->personId, $view->id);

        self::assertSame([], $query->missingFor($organizer, [$view->id]));
    }

    public function test_a_new_version_without_the_flag_keeps_the_participation_complete_and_with_the_flag_it_owes_again(): void
    {
        [$organizer, , $view, $query] = $this->setUpParticipation();
        $doc = $this->publishedDocument($organizer);
        $terms = $this->requirement($organizer, $doc['documentId']);
        $this->decide($organizer, $terms, $doc['versionId'], ConsentDecision::Granted, $view->personId, $view->id);

        $this->publishVersion($organizer, $doc['documentId'], 'Cosmetic change');
        self::assertSame([], $query->missingFor($organizer, [$view->id]), 'S14');

        $this->publishVersion($organizer, $doc['documentId'], 'Material change', reconfirm: true);
        /** @var array<string, list<MissingConfirmation>> $missing */
        $missing = $query->missingFor($organizer, [$view->id]);
        self::assertCount(1, $missing);
        self::assertTrue(current($missing)[0]->reconfirmation, 'S15');
    }

    public function test_an_order_level_requirement_is_checked_against_the_order(): void
    {
        [$organizer, , $view, $query] = $this->setUpParticipation();
        $doc = $this->publishedDocument($organizer);
        $privacy = $this->requirement($organizer, $doc['documentId'], RequirementType::PrivacyNotice, confirmer: \Eventor\Legal\Contract\Confirmer::Orderer);

        self::assertCount(1, $query->missingFor($organizer, [$view->id])[$view->id->toString()]);

        $this->decide($organizer, $privacy, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), order: $view->orderId);

        self::assertSame([], $query->missingFor($organizer, [$view->id]));
    }

    public function test_the_marketing_audience_is_narrowed_to_persons_whose_latest_decision_is_granted(): void
    {
        $organizer = $this->organizer();
        $doc = $this->publishedDocument($organizer);
        $newsletter = $this->requirement($organizer, $doc['documentId'], RequirementType::Consent, mandatory: false, purpose: 'newsletter');
        $granted = PersonId::generate();
        $withdrawn = PersonId::generate();
        $declined = PersonId::generate();
        $silent = PersonId::generate();
        $this->decide($organizer, $newsletter, $doc['versionId'], ConsentDecision::Granted, $granted, ParticipationId::generate());
        $this->decide($organizer, $newsletter, $doc['versionId'], ConsentDecision::Granted, $withdrawn, $p = ParticipationId::generate(), OrderId::generate());
        $this->decide($organizer, $newsletter, $doc['versionId'], ConsentDecision::Withdrawn, $withdrawn, $p);
        $this->decide($organizer, $newsletter, $doc['versionId'], ConsentDecision::Declined, $declined, ParticipationId::generate());

        $audience = $this->service(ConsentQuery::class)->grantedAmong($organizer, RequirementType::Consent, 'newsletter', [$granted, $withdrawn, $declined, $silent]);

        self::assertSame([$granted->toString()], array_map(static fn(PersonId $p): string => $p->toString(), $audience));
        self::assertSame([], $this->service(ConsentQuery::class)->grantedAmong($organizer, RequirementType::Consent, 'another purpose', [$granted]));
        self::assertSame([], $this->service(ConsentQuery::class)->grantedAmong($this->organizer('beta'), RequirementType::Consent, 'newsletter', [$granted]));
    }

    public function test_persons_with_a_granted_likeness_consent_of_an_event(): void
    {
        [$organizer, $event, $view, $query] = $this->setUpParticipation();
        $doc = $this->publishedDocument($organizer);
        $likeness = $this->requirement($organizer, $doc['documentId'], RequirementType::Likeness, mandatory: false);
        $this->decide($organizer, $likeness, $doc['versionId'], ConsentDecision::Granted, $view->personId, $view->id);
        $this->decide($organizer, $likeness, $doc['versionId'], ConsentDecision::Granted, PersonId::generate(), ParticipationId::generate());

        $persons = $query->personsWithGrantedConsent($organizer, $event, RequirementType::Likeness);

        self::assertSame([$view->personId->toString()], array_map(static fn(PersonId $p): string => $p->toString(), $persons));
        self::assertSame([], $query->personsWithGrantedConsent($organizer, EventId::generate(), RequirementType::Likeness));
    }

    /**
     * @return array{OrganizerId, EventId, ParticipationView, ConsentQuery}
     */
    private function setUpParticipation(): array
    {
        $organizer = $this->organizer();
        $event = EventId::generate();
        $view = new ParticipationView(ParticipationId::generate(), OrderId::generate(), PersonId::generate(), $event, ActivityId::generate(), VariantId::generate(), null, ParticipationState::Confirmed, LegalState::Incomplete, AnswersState::Complete, null, 'REG-1', null);
        $stub = new class($view) implements ParticipationQuery {
            public function __construct(private readonly ParticipationView $view) {}

            public function find(OrganizerId $organizer, ParticipationId $id): ParticipationView
            {
                return $id->equals($this->view->id) ? $this->view : throw ParticipationNotFound::forId($id);
            }

            public function findMany(OrganizerId $organizer, array $ids): array
            {
                return array_values(array_filter([$this->view], static fn(ParticipationView $v): bool => \in_array($v->id->toString(), array_map(static fn(ParticipationId $i): string => $i->toString(), $ids), true)));
            }

            public function forCheckIn(OrganizerId $organizer, ParticipationId $id): ParticipationCheckInView
            {
                throw new \LogicException('Not needed.');
            }

            public function findByRegistrationCode(OrganizerId $organizer, string $registrationCode): ?ParticipationId
            {
                return null;
            }

            public function searchForCheckIn(OrganizerId $organizer, ActivityId $activity, string $term, int $limit = 20): array
            {
                return [];
            }
        };

        return [$organizer, $event, $view, new DbalConsentQuery($this->connection(), $this->service(RequirementResolver::class), $stub)];
    }
}
