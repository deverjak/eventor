<?php

declare(strict_types=1);

namespace Eventor\Legal\Contract;

/**
 * What a legal requirement asks for (LEG-04). `consent` and `likeness` are voluntary and revocable (LEG-06, LEG-09), the others are conditions of the registration.
 */
enum RequirementType: string
{
    case Terms = 'terms';
    case PrivacyNotice = 'privacy_notice';
    case HealthDeclaration = 'health_declaration';
    case Consent = 'consent';
    case Likeness = 'likeness';
}
