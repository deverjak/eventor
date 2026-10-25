<?php

declare(strict_types=1);

namespace Eventor\Catalog\Domain\Form;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\SemanticField;

/**
 * The form a new event starts with (EVT-02, "jednoduchá akce"): who takes part and how to reach the orderer. The
 * organizer adds the questions of the event on top (FRM-01..06).
 */
final class DefaultFormSchema
{
    public static function create(): FormSchema
    {
        return FormSchema::of([
            new FieldDefinition('first_name', FieldTypeName::ShortText, 'Jméno', null, true, AnswerScope::Person, ['maxLength' => 100], SemanticField::FirstName),
            new FieldDefinition('last_name', FieldTypeName::ShortText, 'Příjmení', null, true, AnswerScope::Person, ['maxLength' => 100], SemanticField::LastName),
            new FieldDefinition('birth_date', FieldTypeName::Date, 'Datum narození', null, true, AnswerScope::Person, [], SemanticField::BirthDate),
            new FieldDefinition('orderer_first_name', FieldTypeName::ShortText, 'Jméno objednávajícího', null, true, AnswerScope::Order, ['maxLength' => 100], SemanticField::FirstName),
            new FieldDefinition('orderer_last_name', FieldTypeName::ShortText, 'Příjmení objednávajícího', null, true, AnswerScope::Order, ['maxLength' => 100], SemanticField::LastName),
            new FieldDefinition('orderer_email', FieldTypeName::Email, 'E-mail', 'Sem pošleme potvrzení a platební údaje.', true, AnswerScope::Order, [], SemanticField::Email),
            new FieldDefinition('orderer_phone', FieldTypeName::Phone, 'Telefon', null, false, AnswerScope::Order, [], SemanticField::Phone),
        ]);
    }
}
