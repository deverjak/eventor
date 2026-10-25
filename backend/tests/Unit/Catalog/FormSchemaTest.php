<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;
use Eventor\Catalog\Domain\Exception\InvalidFormSchema;
use Eventor\Catalog\Domain\Form\DefaultFormSchema;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Form\SelectionContext;
use Eventor\Shared\Domain\Id\ActivityId;
use PHPUnit\Framework\TestCase;

final class FormSchemaTest extends TestCase
{
    public function test_the_default_form_round_trips_and_has_a_stable_hash(): void
    {
        $schema = DefaultFormSchema::create();

        $again = FormSchema::fromArray($schema->toArray());

        self::assertEquals($schema, $again);
        self::assertSame($schema->sha256(), $again->sha256());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $schema->sha256());
        self::assertSame(['first_name', 'last_name', 'birth_date', 'orderer_first_name', 'orderer_last_name', 'orderer_email'], $schema->requiredKeys());
    }

    public function test_the_hash_ignores_key_order_but_not_content(): void
    {
        $a = FormSchema::fromArray(['fields' => [['key' => 'dog', 'type' => 'short_text', 'label' => 'Pes', 'scope' => 'person', 'required' => true]]]);
        $b = FormSchema::fromArray(['fields' => [['required' => true, 'scope' => 'person', 'label' => 'Pes', 'type' => 'short_text', 'key' => 'dog']]]);
        $c = FormSchema::fromArray(['fields' => [['key' => 'dog', 'type' => 'short_text', 'label' => 'Pes!', 'scope' => 'person', 'required' => true]]]);

        self::assertSame($a->sha256(), $b->sha256());
        self::assertNotSame($a->sha256(), $c->sha256());
    }

    public function test_every_problem_is_reported_with_its_path(): void
    {
        try {
            FormSchema::fromArray(['fields' => [
                ['key' => 'Bad Key', 'type' => 'short_text', 'label' => 'x', 'scope' => 'person'],
                ['key' => 'dup', 'type' => 'short_text', 'label' => 'x', 'scope' => 'person'],
                ['key' => 'dup', 'type' => 'single_choice', 'label' => 'x', 'scope' => 'person'],
                ['key' => 'ghost', 'type' => 'nope', 'label' => 'x', 'scope' => 'person'],
                ['key' => 'when', 'type' => 'date', 'label' => '', 'scope' => 'galaxy', 'required' => 'yes'],
                ['key' => 'info', 'type' => 'info_text', 'label' => 'i', 'scope' => 'order', 'required' => true],
                ['key' => 'age', 'type' => 'short_text', 'label' => 'x', 'scope' => 'person', 'semantic' => 'birth_date'],
                ['key' => 'n', 'type' => 'number', 'label' => 'x', 'scope' => 'person', 'constraints' => ['min' => 10, 'max' => 1, 'bogus' => 1]],
            ]]);
            self::fail('The schema is invalid.');
        } catch (InvalidFormSchema $e) {
            $byPath = [];
            foreach ($e->violations() as $violation) {
                $byPath[$violation['path']][] = $violation['code'];
            }

            self::assertSame(['invalid_value'], $byPath['fields[0].key']);
            self::assertSame(['duplicate_key'], $byPath['fields[2].key']);
            self::assertSame(['invalid_value'], $byPath['fields[2].constraints.choices']);
            self::assertSame(['unknown_type'], $byPath['fields[3].type']);
            self::assertArrayHasKey('fields[4].label', $byPath);
            self::assertArrayHasKey('fields[4].scope', $byPath);
            self::assertArrayHasKey('fields[4].required', $byPath);
            self::assertArrayHasKey('fields[5].required', $byPath);
            self::assertSame(['incompatible_semantic'], $byPath['fields[6].semantic']);
            self::assertSame(['unknown_constraint'], $byPath['fields[7].constraints.bogus']);
            self::assertSame(['invalid_range'], $byPath['fields[7].constraints.min']);
        }
    }

    public function test_conditions_may_only_look_at_earlier_fields_of_the_same_scope(): void
    {
        $fields = [
            ['key' => 'has_dog', 'type' => 'yes_no', 'label' => 'Pes?', 'scope' => 'person'],
            ['key' => 'dog', 'type' => 'short_text', 'label' => 'Jméno psa', 'scope' => 'person', 'condition' => ['type' => 'answer', 'field' => 'has_dog', 'operator' => 'equals', 'value' => true]],
            ['key' => 'later', 'type' => 'short_text', 'label' => 'x', 'scope' => 'person', 'condition' => ['type' => 'answer', 'field' => 'ahead', 'operator' => 'filled']],
            ['key' => 'ahead', 'type' => 'short_text', 'label' => 'x', 'scope' => 'person'],
            ['key' => 'other_scope', 'type' => 'short_text', 'label' => 'x', 'scope' => 'order', 'condition' => ['type' => 'answer', 'field' => 'has_dog', 'operator' => 'filled']],
            ['key' => 'bad_op', 'type' => 'short_text', 'label' => 'x', 'scope' => 'person', 'condition' => ['type' => 'answer', 'field' => 'has_dog', 'operator' => 'equals']],
        ];

        try {
            FormSchema::fromArray(['fields' => $fields]);
            self::fail('Three conditions are invalid.');
        } catch (InvalidFormSchema $e) {
            $paths = array_column($e->violations(), 'path');
            self::assertContains('fields[2].condition.field', $paths);
            self::assertContains('fields[4].condition.field', $paths);
            self::assertContains('fields[5].condition.value', $paths);
            self::assertNotContains('fields[1].condition.field', $paths);
        }
    }

    public function test_activity_and_variant_bindings_and_conditions_decide_which_fields_apply(): void
    {
        $canicross = ActivityId::generate()->toString();
        $trail = ActivityId::generate()->toString();
        $schema = FormSchema::fromArray(['fields' => [
            ['key' => 'dog', 'type' => 'short_text', 'label' => 'Pes', 'scope' => 'person', 'required' => true, 'activityIds' => [$canicross]],
            ['key' => 'vet', 'type' => 'yes_no', 'label' => 'Očkování', 'scope' => 'person', 'condition' => ['type' => 'activity', 'ids' => [$canicross]]],
            ['key' => 'all', 'type' => 'short_text', 'label' => 'Pro všechny', 'scope' => 'person'],
        ]]);

        $onTrail = new SelectionContext([$trail], []);
        $onCanicross = new SelectionContext([$trail, $canicross], []);

        self::assertSame(['all'], $this->applicable($schema, $onTrail));
        self::assertSame(['dog', 'vet', 'all'], $this->applicable($schema, $onCanicross));
    }

    public function test_semantic_fields_are_unique_per_scope(): void
    {
        $this->expectException(InvalidFormSchema::class);

        FormSchema::fromArray(['fields' => [
            ['key' => 'a', 'type' => 'short_text', 'label' => 'a', 'scope' => 'person', 'semantic' => 'first_name'],
            ['key' => 'b', 'type' => 'short_text', 'label' => 'b', 'scope' => 'person', 'semantic' => 'first_name'],
        ]]);
    }

    public function test_the_default_form_carries_the_identity_semantics(): void
    {
        $schema = DefaultFormSchema::create();

        self::assertTrue($schema->hasSemantic(SemanticField::BirthDate));
        self::assertFalse($schema->hasSemantic(SemanticField::GuardianEmail));
        self::assertCount(3, $schema->fieldsFor(AnswerScope::Person));
    }

    /**
     * @return list<string>
     */
    private function applicable(FormSchema $schema, SelectionContext $context): array
    {
        return array_values(array_map(
            static fn($field): string => $field->key,
            array_filter($schema->fields, static fn($field): bool => $field->isApplicable($context)),
        ));
    }
}
