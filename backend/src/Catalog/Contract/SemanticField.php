<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * Fields with meaning for the system (FRM-04): projected into typed columns of the person because computations
 * (age, category, addressing) run on them.
 */
enum SemanticField: string
{
    case FirstName = 'first_name';
    case LastName = 'last_name';
    case BirthDate = 'birth_date';
    case Email = 'email';
    case Phone = 'phone';
    case GuardianFirstName = 'guardian_first_name';
    case GuardianLastName = 'guardian_last_name';
    case GuardianEmail = 'guardian_email';
    case GuardianPhone = 'guardian_phone';
}
