<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Application\Form\Constraint\MinimumAge;
use Eventor\Catalog\Application\Form\Constraint\ValidCzechPhone;
use Eventor\Catalog\Application\Form\Constraint\ValidCzechPhoneValidator;
use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Eventor\Catalog\Domain\Form\FieldTypeName;
use Eventor\Tests\Support\Catalog\FormKit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One test per field type (ADR 007, "AnswerValidatorTest"), plus the two custom constraints.
 */
final class FieldTypeConstraintsTest extends TestCase
{
    private FormKit $kit;

    protected function setUp(): void
    {
        $this->kit = new FormKit('2026-10-25 12:00:00');
    }

    /**
     * @param array<string, mixed> $constraints
     */
    #[DataProvider('cases')]
    public function test_each_type_accepts_and_rejects(FieldTypeName $type, array $constraints, mixed $valid, mixed $invalid, mixed $stored): void
    {
        $field = new FieldDefinition('f', $type, 'Pole', null, true, AnswerScope::Person, $constraints);
        $implementation = $this->kit->registry->get($type);

        self::assertCount(0, $this->kit->validator->validate($valid, $implementation->constraints($field)), 'valid value');
        self::assertGreaterThan(0, \count($this->kit->validator->validate($invalid, $implementation->constraints($field))), 'invalid value');
        self::assertSame($stored, $implementation->normalize($field, $valid));
        self::assertIsArray($implementation->jsonSchema($field));
    }

    /**
     * @return iterable<string, array{FieldTypeName, array<string, mixed>, mixed, mixed, mixed}>
     */
    public static function cases(): iterable
    {
        $sizes = ['choices' => [['value' => 'S', 'label' => 'Small'], ['value' => 'M', 'label' => 'Medium']]];

        yield 'short text' => [FieldTypeName::ShortText, ['maxLength' => 5], 'Jana', 'too long text', 'Jana'];
        yield 'long text' => [FieldTypeName::LongText, ['minLength' => 3], 'Dlouhý text', 'ab', 'Dlouhý text'];
        yield 'number' => [FieldTypeName::Number, ['min' => 1, 'max' => 10, 'integer' => true], 7, 7.5, 7];
        yield 'number out of range' => [FieldTypeName::Number, ['min' => 1, 'max' => 10], 10, 11, 10];
        yield 'date' => [FieldTypeName::Date, ['minDate' => '2000-01-01'], '2010-05-04', '1999-12-31', '2010-05-04'];
        yield 'date format' => [FieldTypeName::Date, [], '2010-05-04', '4. 5. 2010', '2010-05-04'];
        yield 'date max age' => [FieldTypeName::Date, ['maxAge' => 12, 'referenceDate' => '2026-11-22'], '2014-11-23', '2013-11-22', '2014-11-23'];
        yield 'email' => [FieldTypeName::Email, [], 'Jana@Example.TEST', 'jana@', 'jana@example.test'];
        yield 'phone' => [FieldTypeName::Phone, [], '+420 777 123 456', '12345', '+420777123456'];
        yield 'single choice' => [FieldTypeName::SingleChoice, $sizes, 'M', 'XL', 'M'];
        yield 'multi choice' => [FieldTypeName::MultiChoice, $sizes + ['minChoices' => 1, 'maxChoices' => 2], ['S', 'M'], [], ['S', 'M']];
        yield 'multi choice unknown' => [FieldTypeName::MultiChoice, $sizes, ['S'], ['S', 'XL'], ['S']];
        yield 'yes no' => [FieldTypeName::YesNo, [], false, 'yes', false];
        yield 'legal requirement' => [FieldTypeName::LegalRequirement, [], true, false, true];
    }

    public function test_info_text_has_no_constraints_and_no_answer(): void
    {
        $field = new FieldDefinition('info', FieldTypeName::InfoText, 'Pozor', 'Start v 10:00', false, AnswerScope::Order);

        self::assertSame([], $this->kit->registry->get(FieldTypeName::InfoText)->constraints($field));
        self::assertFalse(FieldTypeName::InfoText->hasAnswer());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function phones(): iterable
    {
        yield 'plain' => ['777123456', true];
        yield 'with spaces' => ['777 123 456', true];
        yield 'with country code' => ['+420 777 123 456', true];
        yield 'with 00 prefix' => ['00420777123456', true];
        yield 'dashes' => ['777-123-456', true];
        yield 'too short' => ['77712345', false];
        yield 'starts with zero' => ['077123456', false];
        yield 'foreign' => ['+49 170 1234567', false];
        yield 'letters' => ['777 ABC 456', false];
    }

    #[DataProvider('phones')]
    public function test_valid_czech_phone(string $number, bool $valid): void
    {
        $violations = $this->kit->validator->validate($number, new ValidCzechPhone());

        self::assertSame($valid, 0 === \count($violations), $number);
        self::assertSame($valid, null !== ValidCzechPhoneValidator::canonical($number));
    }

    public function test_valid_czech_phone_ignores_an_empty_value(): void
    {
        self::assertCount(0, $this->kit->validator->validate(null, new ValidCzechPhone()));
        self::assertCount(0, $this->kit->validator->validate('', new ValidCzechPhone()));
    }

    /**
     * @return iterable<string, array{string, int, string|null, bool}>
     */
    public static function ages(): iterable
    {
        yield 'exactly on the birthday' => ['2011-11-21', 15, '2026-11-21', true];
        yield 'one day short' => ['2011-11-22', 15, '2026-11-21', false];
        yield 'older' => ['1990-01-01', 15, '2026-11-21', true];
        yield 'leap day birthday' => ['2012-02-29', 14, '2026-02-28', false];
        yield 'leap day birthday after' => ['2012-02-29', 14, '2026-03-01', true];
        yield 'today as the default reference' => ['2011-10-25', 15, null, true];
        yield 'today, one day short' => ['2011-10-26', 15, null, false];
    }

    #[DataProvider('ages')]
    public function test_minimum_age(string $birthDate, int $years, ?string $reference, bool $valid): void
    {
        $violations = $this->kit->validator->validate($birthDate, new MinimumAge($years, $reference));

        self::assertSame($valid, 0 === \count($violations), $birthDate);
    }
}
