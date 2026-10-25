<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Application\Form\CatalogFormValidator;
use Eventor\Catalog\Application\Query\PublishedFormQuery;
use Eventor\Catalog\Application\Query\View\PublishedFormVersionView;
use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\AnswerSetInput;
use Eventor\Catalog\Contract\Exception\FormValidationFailed;
use Eventor\Catalog\Contract\FormSelection;
use Eventor\Catalog\Contract\FormSubmission;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\EventId;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\Catalog\FormKit;
use PHPUnit\Framework\TestCase;

/**
 * FRM-05, FRM-07, FRM-09: the validator builds its rules from the schema, evaluates the conditions on the server and
 * stores normalised, non-empty answers only.
 */
final class AnswerValidationTest extends TestCase
{
    private FormKit $kit;
    private OrganizerId $organizer;
    private FormVersionId $version;
    private string $canicross;
    private string $trail;

    protected function setUp(): void
    {
        $this->kit = new FormKit();
        $this->organizer = OrganizerId::generate();
        $this->version = FormVersionId::generate();
        $this->canicross = ActivityId::generate()->toString();
        $this->trail = ActivityId::generate()->toString();
    }

    public function test_valid_answers_are_normalised_and_semantic_fields_are_extracted(): void
    {
        $result = $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->trail], [
            'first_name' => '  Jana ',
            'last_name' => 'Nováková',
            'birth_date' => '1990-05-04',
            'email' => 'Jana@Example.TEST ',
            'phone' => '777 123 456',
            'note' => '',
        ]));

        $set = $result->answerSets[0];
        self::assertSame($this->version, $result->formVersionId);
        self::assertSame(['t' => 'short_text', 'v' => 'Jana'], $set->answers['first_name']);
        self::assertSame('jana@example.test', $set->answers['email']['v']);
        self::assertSame('+420777123456', $set->answers['phone']['v']);
        self::assertArrayNotHasKey('note', $set->answers, 'a blank answer is dropped, never stored as an empty value');
        self::assertSame('Jana', $set->semantic->firstName);
        self::assertSame('1990-05-04', $set->semantic->birthDate?->format('Y-m-d'));
        self::assertSame('+420777123456', $set->semantic->phone);
        self::assertSame('p1', $set->subjectRef);
    }

    public function test_every_invalid_answer_is_reported_with_its_path_and_code(): void
    {
        try {
            $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->trail], [
                'first_name' => '',
                'birth_date' => '2010-02-31',
                'email' => 'not-an-email',
                'phone' => '12345',
                'size' => 'XXXL',
                'surprise' => 'x',
            ]));
            self::fail('The answers are invalid.');
        } catch (FormValidationFailed $e) {
            $byPath = [];
            foreach ($e->violations() as $violation) {
                $byPath['' === $violation['path'] ? '' : $violation['path']] = $violation['code'];
            }

            self::assertSame('required', $byPath['p1.first_name']);
            self::assertSame('required', $byPath['p1.last_name']);
            self::assertSame('date', $byPath['p1.birth_date']);
            self::assertSame('email', $byPath['p1.email']);
            self::assertSame('valid_czech_phone', $byPath['p1.phone']);
            self::assertSame('choice', $byPath['p1.size']);
            self::assertSame('unexpected_field', $byPath['p1.surprise']);
        }
    }

    public function test_a_field_bound_to_another_activity_is_neither_required_nor_accepted(): void
    {
        $base = ['first_name' => 'Jana', 'last_name' => 'N', 'birth_date' => '1990-05-04', 'email' => 'a@b.cz'];

        // Trail: the dog field does not apply, sending it is rejected (FRM-07: validation by schema, not by screen).
        try {
            $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->trail], $base + ['dog' => 'Rex']));
            self::fail('The dog field does not apply to the trail.');
        } catch (FormValidationFailed $e) {
            self::assertSame([['path' => 'p1.dog', 'message' => 'This field does not apply to the selection.', 'code' => 'unexpected_field']], $e->violations());
        }

        // Canicross: the dog is required.
        try {
            $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->canicross], $base));
            self::fail('The dog is required for canicross.');
        } catch (FormValidationFailed $e) {
            self::assertSame('p1.dog', $e->violations()[0]['path']);
            self::assertSame('required', $e->violations()[0]['code']);
        }

        $ok = $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->canicross], $base + ['dog' => 'Rex']));
        self::assertSame('Rex', $ok->answerSets[0]->answers['dog']['v']);
    }

    public function test_a_condition_on_an_answer_shows_the_dependent_field(): void
    {
        $base = ['first_name' => 'Jana', 'last_name' => 'N', 'birth_date' => '1990-05-04', 'email' => 'a@b.cz'];

        $without = $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->trail], $base + ['vegetarian' => false]));
        self::assertFalse($without->answerSets[0]->answers['vegetarian']['v'], 'false is an answer, not an empty value');

        $this->expectException(FormValidationFailed::class);
        $this->validator()->validate($this->organizer, $this->version, $this->submission([$this->trail], $base + ['vegetarian' => true]));
    }

    public function test_missing_required_fields_follow_the_selection_and_ignore_answer_conditions(): void
    {
        $missing = $this->validator()->missingRequiredFields(
            $this->organizer,
            $this->version,
            AnswerScope::Person,
            new FormSelection([ActivityId::fromString($this->canicross)], []),
            ['first_name', 'last_name'],
        );

        self::assertSame(['birth_date', 'email', 'dog'], $missing);
    }

    public function test_the_minimum_age_counts_on_the_reference_date(): void
    {

        $rules = FormSchema::fromArray(['fields' => [
            ['key' => 'born', 'type' => 'date', 'label' => 'Narození', 'scope' => 'person', 'required' => true, 'constraints' => ['minAge' => 15, 'referenceDate' => '2026-11-21']],
        ]]);
        $validator = $this->validatorFor($rules);

        // 15 years exactly on the reference day: allowed; one day short: refused.
        $validator->validate($this->organizer, $this->version, new FormSubmission(new FormSelection([], []), [new AnswerSetInput(AnswerScope::Person, 'p1', ['born' => '2011-11-21'])]));
        $this->expectException(FormValidationFailed::class);
        $validator->validate($this->organizer, $this->version, new FormSubmission(new FormSelection([], []), [new AnswerSetInput(AnswerScope::Person, 'p1', ['born' => '2011-11-22'])]));


    }

    private function validator(): CatalogFormValidator
    {
        return $this->validatorFor(FormSchema::fromArray(['fields' => [
            ['key' => 'first_name', 'type' => 'short_text', 'label' => 'Jméno', 'scope' => 'person', 'required' => true, 'semantic' => 'first_name'],
            ['key' => 'last_name', 'type' => 'short_text', 'label' => 'Příjmení', 'scope' => 'person', 'required' => true, 'semantic' => 'last_name'],
            ['key' => 'birth_date', 'type' => 'date', 'label' => 'Narození', 'scope' => 'person', 'required' => true, 'semantic' => 'birth_date'],
            ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'person', 'required' => true, 'semantic' => 'email'],
            ['key' => 'phone', 'type' => 'phone', 'label' => 'Telefon', 'scope' => 'person', 'semantic' => 'phone'],
            ['key' => 'note', 'type' => 'long_text', 'label' => 'Poznámka', 'scope' => 'person'],
            ['key' => 'size', 'type' => 'single_choice', 'label' => 'Tričko', 'scope' => 'person', 'constraints' => ['choices' => [['value' => 'S', 'label' => 'S'], ['value' => 'M', 'label' => 'M']]]],
            ['key' => 'dog', 'type' => 'short_text', 'label' => 'Jméno psa', 'scope' => 'person', 'required' => true, 'activityIds' => [$this->canicross]],
            ['key' => 'vegetarian', 'type' => 'yes_no', 'label' => 'Vegetarián', 'scope' => 'person'],
            ['key' => 'allergies', 'type' => 'short_text', 'label' => 'Alergie', 'scope' => 'person', 'required' => true, 'condition' => ['type' => 'answer', 'field' => 'vegetarian', 'operator' => 'equals', 'value' => true]],
        ]]));
    }

    private function validatorFor(FormSchema $schema): CatalogFormValidator
    {
        $stored = new PublishedFormVersionView($this->version, EventId::generate(), 1, new \DateTimeImmutable('2026-10-25'), $schema->sha256(), $schema);
        $forms = self::createStub(PublishedFormQuery::class);
        $forms->method('version')->willReturn($stored);

        return new CatalogFormValidator($forms, $this->kit->builder, $this->kit->registry, $this->kit->validator);
    }

    /**
     * @param list<string>         $activityIds
     * @param array<string, mixed> $answers
     */
    private function submission(array $activityIds, array $answers): FormSubmission
    {
        return new FormSubmission(
            new FormSelection(array_map(ActivityId::fromString(...), $activityIds), []),
            [new AnswerSetInput(AnswerScope::Person, 'p1', $answers)],
        );
    }
}
