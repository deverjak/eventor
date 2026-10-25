<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * A Czech phone number: nine digits, optionally with +420 / 00420 and spaces ("+420 777 123 456").
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER)]
final class ValidCzechPhone extends Constraint
{
    public string $message = 'This is not a valid Czech phone number.';
}
