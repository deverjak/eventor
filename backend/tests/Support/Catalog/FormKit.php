<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Catalog;

use Eventor\Catalog\Application\Form\AnswerConstraintBuilder;
use Eventor\Catalog\Application\Form\Constraint\MinimumAge;
use Eventor\Catalog\Application\Form\Constraint\MinimumAgeValidator;
use Eventor\Catalog\Application\Form\FieldTypeRegistry;
use Eventor\Catalog\Application\Form\Type\DateType;
use Eventor\Catalog\Application\Form\Type\EmailType;
use Eventor\Catalog\Application\Form\Type\InfoTextType;
use Eventor\Catalog\Application\Form\Type\LegalRequirementType;
use Eventor\Catalog\Application\Form\Type\LongTextType;
use Eventor\Catalog\Application\Form\Type\MultiChoiceType;
use Eventor\Catalog\Application\Form\Type\NumberType;
use Eventor\Catalog\Application\Form\Type\PhoneType;
use Eventor\Catalog\Application\Form\Type\ShortTextType;
use Eventor\Catalog\Application\Form\Type\SingleChoiceType;
use Eventor\Catalog\Application\Form\Type\YesNoType;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Hand-made form machinery for unit tests (no kernel): the eleven field types, the registry, the constraint builder and
 * a validator that knows the clock-dependent `MinimumAge`.
 */
final class FormKit
{
    public readonly MockClock $clock;
    public readonly FieldTypeRegistry $registry;
    public readonly AnswerConstraintBuilder $builder;
    public readonly ValidatorInterface $validator;

    public function __construct(string $now = '2026-10-25 12:00:00')
    {
        $this->clock = new MockClock($now);
        $types = [
            'short_text' => new ShortTextType(),
            'long_text' => new LongTextType(),
            'number' => new NumberType(),
            'date' => new DateType($this->clock),
            'email' => new EmailType(),
            'phone' => new PhoneType(),
            'single_choice' => new SingleChoiceType(),
            'multi_choice' => new MultiChoiceType(),
            'yes_no' => new YesNoType(),
            'info_text' => new InfoTextType(),
            'legal_requirement' => new LegalRequirementType(),
        ];
        $this->registry = new FieldTypeRegistry(new ServiceLocator(array_map(static fn(object $type): \Closure => static fn(): object => $type, $types)));
        $this->builder = new AnswerConstraintBuilder($this->registry);

        $clock = $this->clock;
        $this->validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new class($clock) extends ConstraintValidatorFactory {
                public function __construct(private readonly MockClock $clock)
                {
                    parent::__construct();
                }

                public function getInstance(Constraint $constraint): ConstraintValidatorInterface
                {
                    return $constraint instanceof MinimumAge ? new MinimumAgeValidator($this->clock) : parent::getInstance($constraint);
                }
            })
            ->getValidator();
    }
}
