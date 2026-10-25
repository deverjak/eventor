<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * A birth date (`Y-m-d`) of a person who is at least `years` old on `referenceDate` (EVT-06, "Trail 10 km, min. věk
 * 15"). Without a reference date the day of validation counts.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER)]
final class MinimumAge extends Constraint
{
    public string $message = 'The minimum age is {{ years }} years.';

    public function __construct(
        public int $years = 0,
        public ?string $referenceDate = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}
