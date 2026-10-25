<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

use Eventor\Catalog\Contract\SemanticField;

/**
 * Every field type the editor offers (FRM-01). `FieldTypeRegistryPass` fails the container build when a case has no
 * `#[AsFieldType]` implementation, so adding a case without its validator cannot ship.
 */
enum FieldTypeName: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Number = 'number';
    case Date = 'date';
    case Email = 'email';
    case Phone = 'phone';
    case SingleChoice = 'single_choice';
    case MultiChoice = 'multi_choice';
    case YesNo = 'yes_no';
    case InfoText = 'info_text';
    case LegalRequirement = 'legal_requirement';

    /** Info text is displayed only; it never carries an answer. */
    public function hasAnswer(): bool
    {
        return self::InfoText !== $this;
    }

    public function isChoice(): bool
    {
        return self::SingleChoice === $this || self::MultiChoice === $this;
    }

    /**
     * Keys of the `constraints` object that the type understands (FRM-02).
     *
     * @return list<string>
     */
    public function allowedConstraints(): array
    {
        return match ($this) {
            self::ShortText, self::LongText => ['minLength', 'maxLength'],
            self::Number => ['min', 'max', 'integer'],
            self::Date => ['minDate', 'maxDate', 'minAge', 'maxAge', 'referenceDate'],
            self::Email, self::Phone => [],
            self::SingleChoice => ['choices'],
            self::MultiChoice => ['choices', 'minChoices', 'maxChoices'],
            self::YesNo, self::InfoText => [],
            self::LegalRequirement => ['requirementId'],
        };
    }

    /** Whether a semantic field (FRM-04) may be carried by a field of this type. */
    public function carries(SemanticField $semantic): bool
    {
        return match ($semantic) {
            SemanticField::FirstName, SemanticField::LastName, SemanticField::GuardianFirstName, SemanticField::GuardianLastName => self::ShortText === $this,
            SemanticField::BirthDate => self::Date === $this,
            SemanticField::Email, SemanticField::GuardianEmail => self::Email === $this,
            SemanticField::Phone, SemanticField::GuardianPhone => self::Phone === $this,
        };
    }
}
