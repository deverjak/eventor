<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form\Type;

use Eventor\Catalog\Application\Form\AsFieldType;
use Eventor\Catalog\Application\Form\Constraint\MinimumAge;
use Eventor\Catalog\Domain\Form\FieldDefinition;
use Psr\Clock\ClockInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A calendar date (`Y-m-d`). Limits: `minDate` / `maxDate`, and for birth dates `minAge` / `maxAge` counted on
 * `referenceDate` (default: today), see EVT-06 and EVT-08.
 */
#[AsFieldType('date')]
final class DateType extends AbstractFieldType
{
    public function __construct(private readonly ClockInterface $clock) {}

    public function constraints(FieldDefinition $field): array
    {
        $constraints = [new Assert\Type('string'), new Assert\Date()];
        $c = $field->constraints;
        if (\is_string($c['minDate'] ?? null)) {
            $constraints[] = new Assert\GreaterThanOrEqual($c['minDate']);
        }
        if (\is_string($c['maxDate'] ?? null)) {
            $constraints[] = new Assert\LessThanOrEqual($c['maxDate']);
        }
        $reference = \is_string($c['referenceDate'] ?? null) ? $c['referenceDate'] : null;
        if (\is_int($c['minAge'] ?? null)) {
            $constraints[] = new MinimumAge($c['minAge'], $reference);
        }
        if (\is_int($c['maxAge'] ?? null)) {
            // The oldest allowed person is still younger than maxAge + 1 on the reference day.
            $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
            $parsed = null === $reference ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $reference);
            $on = false === $parsed ? $now : $parsed;
            $constraints[] = new Assert\GreaterThan(
                $on->modify(\sprintf('-%d years', $c['maxAge'] + 1))->format('Y-m-d'),
                message: \sprintf('The maximum age is %d years.', $c['maxAge']),
            );
        }

        return $constraints;
    }

    public function jsonSchema(FieldDefinition $field): array
    {
        $schema = ['type' => 'string', 'format' => 'date'];
        foreach (['minDate' => 'x-min-date', 'maxDate' => 'x-max-date', 'minAge' => 'x-min-age', 'maxAge' => 'x-max-age', 'referenceDate' => 'x-reference-date'] as $from => $to) {
            if (isset($field->constraints[$from])) {
                $schema[$to] = $field->constraints[$from];
            }
        }

        return $this->describe($field, $schema);
    }
}
