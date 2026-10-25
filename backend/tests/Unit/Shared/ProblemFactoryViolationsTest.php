<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Catalog\Contract\Exception\FormValidationFailed;
use Eventor\Catalog\Contract\FormViolation;
use Eventor\Legal\Contract\Exception\RequirementNotSatisfied;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;
use Eventor\Shared\Domain\Id\LegalRequirementId;
use Eventor\Shared\UserInterface\Http\Problem\ProblemFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * 422 problems of domain exceptions list the offending fields (`violations`), like request validation does.
 */
final class ProblemFactoryViolationsTest extends TestCase
{
    public function test_form_validation_failed_lists_every_field(): void
    {
        $problem = $this->factory()->fromThrowable(new FormValidationFailed([
            new FormViolation('persons[1].birth_date', 'date', 'This value is not a valid date.'),
            new FormViolation('persons[1].first_name', 'required', 'This answer is required.'),
        ]), 'c-1');

        self::assertSame('form_invalid', $problem->code);
        self::assertSame(422, $problem->status);
        self::assertSame([
            ['path' => 'persons[1].birth_date', 'message' => 'This value is not a valid date.', 'code' => 'date'],
            ['path' => 'persons[1].first_name', 'message' => 'This answer is required.', 'code' => 'required'],
        ], $problem->violations);
        self::assertSame(['violationCount' => 2], $problem->context);
    }

    public function test_legal_requirement_not_satisfied_lists_the_missing_confirmations(): void
    {
        $problem = $this->factory()->fromThrowable(new RequirementNotSatisfied([LegalRequirementId::generate()]), null);

        self::assertSame('missing_confirmation', $problem->code);
        self::assertCount(1, $problem->violations);
        self::assertSame('missing_confirmation', $problem->violations[0]['code'] ?? null);
        self::assertStringStartsWith('confirmations.', $problem->violations[0]['path']);
    }

    public function test_an_exception_that_implements_the_interface_is_read_through_it(): void
    {
        $exception = new #[AsProblem(code: 'custom', status: 422)] class extends DomainException implements ProvidesViolations {
            public function violations(): array
            {
                return [['path' => 'a', 'message' => 'broken', 'code' => 'x']];
            }
        };

        self::assertSame([['path' => 'a', 'message' => 'broken', 'code' => 'x']], $this->factory()->fromThrowable($exception, null)->violations);
    }

    public function test_an_ordinary_domain_exception_has_no_violations(): void
    {
        $problem = $this->factory()->fromThrowable(new class('plain') extends DomainException {}, null);

        self::assertSame([], $problem->violations);
    }

    private function factory(): ProblemFactory
    {
        return new ProblemFactory(new IdentityTranslator());
    }
}
