<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Identity\Domain\Organizer\SlugAlreadyTaken;
use Eventor\Shared\Application\Idempotency\IdempotencyKeyReused;
use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Infrastructure\Serializer\ProblemNormalizer;
use Eventor\Shared\UserInterface\Http\Problem\ProblemFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ProblemFactoryTest extends TestCase
{
    private ProblemFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ProblemFactory(new Translator('en'));
    }

    public function test_a_domain_exception_uses_its_as_problem_attribute(): void
    {
        $problem = $this->factory->fromThrowable(SlugAlreadyTaken::create('klub'), 'corr-1');

        self::assertSame('organizer_slug_taken', $problem->code);
        self::assertSame(409, $problem->status);
        self::assertSame('The organizer address "klub" is already taken.', $problem->detail);
        self::assertSame(['slug' => 'klub'], $problem->context);
        self::assertSame('corr-1', $problem->correlationId);
        self::assertSame('https://eventor.cz/problems/organizer-slug-taken', $problem->type());
    }

    public function test_next_steps_of_a_domain_exception_are_exposed(): void
    {
        $exception = new #[AsProblem(code: 'capacity_exhausted', status: 409)] class('Full.') extends DomainException {
            public function nextSteps(): array
            {
                return [['action' => 'join_waitlist', 'href' => '/api/public/waitlist']];
            }
        };

        $problem = $this->factory->fromThrowable($exception, null);

        self::assertSame([['action' => 'join_waitlist', 'href' => '/api/public/waitlist']], $problem->nextSteps);
    }

    public function test_a_domain_exception_without_the_attribute_is_a_422(): void
    {
        $problem = $this->factory->fromThrowable(new class('Rule broken.') extends DomainException {}, null);

        self::assertSame(422, $problem->status);
        self::assertSame('domain_rule_violated', $problem->code);
    }

    public function test_exceptions_wrapped_by_the_bus_are_unwrapped(): void
    {
        $wrapped = new HandlerFailedException(new Envelope(new \stdClass()), [IdempotencyKeyReused::create()]);

        $problem = $this->factory->fromThrowable($wrapped, null);

        self::assertSame('idempotency_key_reused', $problem->code);
        self::assertSame(409, $problem->status);
    }

    public function test_http_exceptions_map_by_status(): void
    {
        self::assertSame('not_found', $this->factory->fromThrowable(new NotFoundHttpException('Organizer not found.'), null)->code);
        self::assertSame('forbidden', $this->factory->fromThrowable(new AccessDeniedHttpException(), null)->code);
        self::assertSame('rate_limited', $this->factory->fromThrowable(new TooManyRequestsHttpException(30), null)->code);
        self::assertSame(429, $this->factory->fromThrowable(new TooManyRequestsHttpException(30), null)->status);
    }

    public function test_validation_failures_list_every_violation_with_its_path(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'email', '', code: NotBlank::IS_BLANK_ERROR),
            new ConstraintViolation('Too short.', null, [], null, 'password', 'x'),
        ]);

        $problem = $this->factory->fromThrowable(new ValidationFailedException(new \stdClass(), $violations), null);

        self::assertSame(422, $problem->status);
        self::assertSame('validation_failed', $problem->code);
        self::assertSame('email', $problem->violations[0]['path']);
        self::assertSame(NotBlank::IS_BLANK_ERROR, $problem->violations[0]['code'] ?? null);
        self::assertSame('password', $problem->violations[1]['path']);
    }

    public function test_unknown_throwables_never_leak_their_message(): void
    {
        $problem = $this->factory->fromThrowable(new \RuntimeException('SQLSTATE[23505] secret table detail'), 'corr');

        self::assertSame(500, $problem->status);
        self::assertSame('internal_error', $problem->code);
        self::assertStringNotContainsString('SQLSTATE', (string) $problem->detail);
        self::assertSame('corr', $problem->correlationId);
    }

    public function test_the_normalizer_emits_rfc_9457_members_and_omits_empty_ones(): void
    {
        $problem = $this->factory->fromThrowable(new NotFoundHttpException('Organizer not found.'), 'corr-9');

        self::assertSame([
            'type' => 'https://eventor.cz/problems/not-found',
            'title' => 'Not Found',
            'status' => 404,
            'code' => 'not_found',
            'detail' => 'Organizer not found.',
            'correlationId' => 'corr-9',
        ], (new ProblemNormalizer())->normalize($problem));
    }
}
