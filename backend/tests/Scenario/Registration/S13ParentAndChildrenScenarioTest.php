<?php

declare(strict_types=1);

namespace Eventor\Tests\Scenario\Registration;

use Eventor\Tests\Support\Catalog\Json;
use Eventor\Tests\Support\Registration\RegistrationScenarioCase;

/**
 * S13 (REG-03, REG-06, LEG-11): a parent registers himself for Saturday and two children for Sunday. One order, three
 * persons, three participations, the guardian confirmation only for the children (and given by the parent), one payment
 * request. Also the registration form for the combination (FRM-11, the public side) and S15 on the same data.
 */
final class S13ParentAndChildrenScenarioTest extends RegistrationScenarioCase
{
    /** @var array{terms: array{requirement: string, version: string}, guardian: array{requirement: string, version: string}} */
    private array $legal;

    /**
     * @return array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string}
     */
    private function event(): array
    {
        $this->connectPairrot();
        $event = $this->openEvent(kids: true, methods: ['bank_transfer']);
        $kids = (string) $event['kids'];
        $this->request('PUT', self::BASE.'/events/'.$event['eventId'].'/form/draft', ['fields' => [
            ['key' => 'first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'person', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'birth_date', 'type' => 'date', 'label' => 'Datum narození', 'scope' => 'person', 'required' => true, 'semantic' => 'birth_date'],
            ['key' => 'guardian_first_name', 'type' => 'short_text', 'label' => 'Jméno zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_first_name', 'activityIds' => [$kids]],
            ['key' => 'guardian_last_name', 'type' => 'short_text', 'label' => 'Příjmení zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_last_name', 'activityIds' => [$kids]],
            ['key' => 'guardian_email', 'type' => 'email', 'label' => 'E-mail zástupce', 'scope' => 'person', 'required' => true, 'semantic' => 'guardian_email', 'activityIds' => [$kids]],
            ['key' => 'orderer_first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'order', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'orderer_last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'order', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'orderer_email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'order', 'required' => true, 'semantic' => 'email'],
        ]]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());

        $terms = $this->legalDocument($event['eventId'], 'Podmínky účasti', ['type' => 'terms', 'purpose' => 'Podmínky', 'confirmer' => 'each_participant', 'mandatory' => true]);
        $guardian = $this->legalDocument($event['eventId'], 'Souhlas zástupce', ['type' => 'health_declaration', 'purpose' => 'Zdravotní způsobilost dítěte', 'confirmer' => 'guardian', 'mandatory' => true, 'activityId' => $kids]);
        $this->legal = ['terms' => $terms, 'guardian' => $guardian];

        $this->publishAndOpen($event['eventId']);

        return $event;
    }

    /**
     * @param array<string, mixed> $requirement
     *
     * @return array{requirement: string, version: string}
     */
    private function legalDocument(string $eventId, string $title, array $requirement): array
    {
        $document = Json::str($this->request('POST', self::BASE.'/legal/documents', ['level' => 'event', 'scopeId' => $eventId, 'title' => $title]) ?? [], 'documentId');
        $version = Json::str($this->request('POST', self::BASE.'/legal/documents/'.$document.'/versions', ['kind' => 'text', 'text' => "# $title\n\nText."]) ?? [], 'versionId');
        $this->request('POST', self::BASE.'/legal/documents/'.$document.'/versions/'.$version.'/publish', []);
        $created = $this->request('POST', self::BASE.'/legal/requirements', ['documentId' => $document] + $requirement);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());

        return ['requirement' => Json::str($created ?? [], 'requirementId'), 'version' => $version];
    }

    /**
     * @param array{eventId: string, slug: string, trail: string, trailVariant: string, kids: ?string, kidsVariant: ?string} $event
     *
     * @return array<string, mixed>
     */
    private function familyOrder(array $event): array
    {
        $decision = fn(string $kind, string $ref): array => [
            'requirementId' => $this->legal[$kind]['requirement'],
            'documentVersionId' => $this->legal[$kind]['version'],
            'decision' => 'granted',
            'personRef' => $ref,
        ];

        return [
            'items' => [
                ['personRef' => 'otec', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']],
                ['personRef' => 'dite1', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant']],
                ['personRef' => 'dite2', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant']],
            ],
            'persons' => [
                ['personRef' => 'otec', 'answers' => ['first_name' => 'Karel', 'last_name' => 'Novák', 'birth_date' => '1985-03-04']],
                ['personRef' => 'dite1', 'answers' => ['first_name' => 'Anna', 'last_name' => 'Nováková', 'birth_date' => '2018-02-03', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => 'karel@example.test']],
                ['personRef' => 'dite2', 'answers' => ['first_name' => 'Boris', 'last_name' => 'Novák', 'birth_date' => '2016-05-06', 'guardian_first_name' => 'Karel', 'guardian_last_name' => 'Novák', 'guardian_email' => 'karel@example.test']],
            ],
            'orderAnswers' => ['orderer_first_name' => 'Karel', 'orderer_last_name' => 'Novák', 'orderer_email' => 'karel@example.test'],
            'decisions' => [
                $decision('terms', 'otec'),
                $decision('terms', 'dite1'), $decision('guardian', 'dite1'),
                $decision('terms', 'dite2'), $decision('guardian', 'dite2'),
            ],
            'paymentMethod' => 'bank_transfer',
        ];
    }

    public function test_s13_one_order_three_persons_three_participations_and_guardian_confirmations_for_the_children_only(): void
    {
        $event = $this->event();

        $placed = $this->placeOrder($this->familyOrder($event));

        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
        $order = Json::str($placed ?? [], 'orderId');
        $this->drain();

        self::assertSame(3, $this->dbInt('SELECT count(*) FROM registration.person WHERE order_id = ?', [$order]), 'the guardian is the father, not a fourth person');
        self::assertSame(3, $this->dbInt('SELECT count(*) FROM registration.participation WHERE order_id = ?', [$order]));
        self::assertSame(50000, $this->dbInt('SELECT amount_due_minor FROM registration.orders WHERE id = ?', [$order]));
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM payments.payment_request WHERE order_id = ?', [$order]), 'one payment request for the whole order');
        self::assertSame(50000, $this->dbInt('SELECT amount_minor FROM payments.payment_request WHERE order_id = ?', [$order]));

        $guardianRows = $this->dbRows(
            'SELECT subject.first_name AS child, decider.first_name AS decided_by, c.order_id FROM legal.confirmation c
               JOIN registration.person subject ON subject.id = c.person_id
               LEFT JOIN registration.person decider ON decider.id = c.decided_by_person_id
              WHERE c.requirement_id = ? ORDER BY subject.first_name',
            [$this->legal['guardian']['requirement']],
        );
        self::assertSame(['Anna', 'Boris'], array_column($guardianRows, 'child'));
        self::assertSame(['Karel', 'Karel'], array_column($guardianRows, 'decided_by'));
        self::assertSame([$order, $order], array_column($guardianRows, 'order_id'), 'Legal gets the order id');
        self::assertSame(3, $this->dbInt('SELECT count(*) FROM legal.confirmation WHERE requirement_id = ?', [$this->legal['terms']['requirement']]));
        self::assertSame(['complete'], $this->dbColumn('SELECT DISTINCT legal_state FROM registration.participation WHERE order_id = ?', [$order]));
    }

    public function test_the_guardian_confirmation_is_required_for_the_children_and_a_guardian_must_be_named(): void
    {
        $event = $this->event();
        $body = $this->familyOrder($event);

        $withoutDecision = $body;
        $withoutDecision['decisions'] = array_values(array_filter(Json::arr($body, 'decisions'), fn(mixed $decision): bool => \is_array($decision) && !('dite2' === Json::str($decision, 'personRef') && $this->legal['guardian']['requirement'] === Json::str($decision, 'requirementId'))));
        $this->placeOrder($withoutDecision);
        $problem = $this->assertProblem(422, 'missing_confirmation');
        self::assertContains($this->legal['guardian']['requirement'], array_map(static fn(mixed $path): string => substr(Json::str($path), \strlen('confirmations.')), Json::column(Json::arr($problem, 'violations'), 'path')));

        $this->placeOrder($body + ['website' => '']);
        self::assertResponseStatusCodeSame(201, (string) $this->client->getResponse()->getContent());
    }

    public function test_the_registration_form_names_the_requirements_per_person_and_the_guardian_ones_only_for_minors(): void
    {
        $event = $this->event();

        $form = $this->request('POST', self::PUBLIC.'vikend/registration-form', ['items' => [
            ['itemRef' => 'otec', 'activityId' => $event['trail'], 'variantId' => $event['trailVariant']],
            ['itemRef' => 'dite1', 'activityId' => (string) $event['kids'], 'variantId' => (string) $event['kidsVariant'], 'minor' => true],
        ]]);

        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $father = Json::arr($form ?? [], 'items.0');
        $child = Json::arr($form ?? [], 'items.1');
        self::assertSame([$this->legal['terms']['requirement']], Json::column(Json::arr($father, 'requirements'), 'requirementId'));
        self::assertEqualsCanonicalizing([$this->legal['terms']['requirement'], $this->legal['guardian']['requirement']], Json::column(Json::arr($child, 'requirements'), 'requirementId'));
        self::assertNotContains('guardian_first_name', Json::column(Json::arr($father, 'fields'), 'key'), 'guardian fields are asked only for the children');
        self::assertContains('guardian_first_name', Json::column(Json::arr($child, 'fields'), 'key'));
        self::assertNotEmpty(Json::str($form ?? [], 'formToken'));
    }

    public function test_s15_a_new_version_with_reconfirmation_flags_the_participations_and_the_participant_confirms(): void
    {
        $event = $this->event();
        $placed = $this->placeOrder($this->familyOrder($event));
        $order = Json::str($placed ?? [], 'orderId');
        $auth = self::selfService($placed ?? []);
        $this->drain();

        // The organizer publishes a new version of the terms that has to be confirmed again.
        $this->login(self::OWNER);
        $document = $this->dbString('SELECT document_id FROM legal.legal_requirement WHERE id = ?', [$this->legal['terms']['requirement']]);
        $draft = $this->request('POST', self::BASE.'/legal/documents/'.$document.'/versions', ['kind' => 'text', 'text' => "# Nové podmínky\n\nZměna."]);
        $next = Json::str($draft ?? [], 'versionId');
        $this->request('POST', self::BASE.'/legal/documents/'.$document.'/versions/'.$next.'/publish', ['requiresReconfirmation' => true]);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->drain();

        self::assertSame(['reconfirmation_required'], $this->dbColumn('SELECT DISTINCT legal_state FROM registration.participation WHERE order_id = ?', [$order]), 'the lifecycle state is untouched, the legal dimension says it');
        self::assertSame(['pending'], $this->dbColumn('SELECT DISTINCT state FROM registration.participation WHERE order_id = ?', [$order]));

        // The participant confirms the new version for everybody through self-service.
        $this->client->getCookieJar()->clear();
        $page = $this->request('GET', '/api/self-service/order', null, $auth);
        $decisions = [];
        foreach (Json::arr($page ?? [], 'participations') as $participation) {
            $participation = Json::arr($participation);
            self::assertNotEmpty(Json::at($participation, 'missingConfirmations'));
            $decisions[] = ['requirementId' => $this->legal['terms']['requirement'], 'participationId' => Json::str($participation, 'id'), 'decision' => 'granted'];
        }
        $this->request('POST', '/api/self-service/confirmations', ['decisions' => $decisions], $auth);
        self::assertResponseIsSuccessful((string) $this->client->getResponse()->getContent());
        $this->drain();

        self::assertSame(['complete'], $this->dbColumn('SELECT DISTINCT legal_state FROM registration.participation WHERE order_id = ?', [$order]));
    }
}
