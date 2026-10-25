<?php

declare(strict_types=1);

namespace Eventor\Shared\UserInterface\Http\Problem;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\ValidationFailedException as MessengerValidationFailedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Exception -> status mapping (chapter 08, "Chyby"). Domain exceptions say what they are with `#[AsProblem]`;
 * framework exceptions are mapped by status. Unknown throwables become `500 internal_error` without a stack trace.
 */
final readonly class ProblemFactory
{
    private const array HTTP_CODES = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        406 => 'not_acceptable',
        409 => 'conflict',
        415 => 'unsupported_media_type',
        422 => 'validation_failed',
        429 => 'rate_limited',
        503 => 'service_unavailable',
    ];

    public function __construct(private TranslatorInterface $translator) {}

    public function fromThrowable(\Throwable $throwable, ?string $correlationId): Problem
    {
        $throwable = $this->unwrap($throwable);

        if ($throwable instanceof DomainException) {
            return $this->fromDomain($throwable, $correlationId);
        }

        $validation = $this->validationFailure($throwable);
        if (null !== $validation) {
            return new Problem(
                'validation_failed',
                422,
                $this->title('validation_failed', 'Request validation failed'),
                'The request does not satisfy the constraints; see violations.',
                violations: $validation,
                correlationId: $correlationId,
            );
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $code = self::HTTP_CODES[$status] ?? 'http_'.$status;

            return new Problem(
                $code,
                $status,
                $this->title($code, $this->statusText($status)),
                $this->safeHttpDetail($throwable),
                correlationId: $correlationId,
            );
        }

        return new Problem(
            'internal_error',
            500,
            $this->title('internal_error', 'Internal server error'),
            'The server failed to process the request. Quote the correlation id when reporting it.',
            correlationId: $correlationId,
        );
    }

    private function fromDomain(DomainException $exception, ?string $correlationId): Problem
    {
        $attributes = (new \ReflectionClass($exception))->getAttributes(AsProblem::class);
        $meta = [] === $attributes ? new AsProblem('domain_rule_violated', 422) : $attributes[0]->newInstance();

        return new Problem(
            $meta->code,
            $meta->status,
            $this->title($meta->code, $this->statusText($meta->status)),
            $exception->getMessage(),
            $exception->context(),
            $exception->nextSteps(),
            $this->violationsOf($exception),
            $correlationId,
        );
    }

    /**
     * Exceptions that implement ProvidesViolations list their offending fields.
     *
     * @return list<array{path: string, message: string, code?: string}>
     */
    private function violationsOf(DomainException $exception): array
    {
        return $exception instanceof ProvidesViolations ? $exception->violations() : [];
    }

    private function unwrap(\Throwable $throwable): \Throwable
    {
        if ($throwable instanceof HandlerFailedException) {
            $wrapped = array_values($throwable->getWrappedExceptions());

            return [] === $wrapped ? $throwable : $this->unwrap($wrapped[0]);
        }
        if ($throwable instanceof DelayedMessageHandlingException) {
            $wrapped = array_values($throwable->getWrappedExceptions());

            return [] === $wrapped ? $throwable : $this->unwrap($wrapped[0]);
        }

        return $throwable;
    }

    /**
     * @return list<array{path: string, message: string, code?: string}>|null
     */
    private function validationFailure(\Throwable $throwable): ?array
    {
        // MapRequestPayload wraps the validator exception in an HttpException (422); the Messenger validation
        // middleware throws its own ValidationFailedException.
        $candidates = [$throwable, $throwable->getPrevious()];
        $failure = null;
        foreach ($candidates as $candidate) {
            if ($candidate instanceof ValidationFailedException || $candidate instanceof MessengerValidationFailedException) {
                $failure = $candidate;
                break;
            }
        }
        if (null === $failure) {
            return null;
        }

        $violations = [];
        foreach ($failure->getViolations() as $violation) {
            $entry = ['path' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
            if (null !== $violation->getCode()) {
                $entry['code'] = $violation->getCode();
            }
            $violations[] = $entry;
        }

        return $violations;
    }

    private function title(string $code, string $fallback): string
    {
        $key = 'problem.'.$code.'.title';
        $translated = $this->translator->trans($key, [], 'problems');

        return $translated === $key ? $fallback : $translated;
    }

    private function statusText(int $status): string
    {
        return \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Error';
    }

    private function safeHttpDetail(HttpExceptionInterface $exception): ?string
    {
        // 4xx messages written by the framework or our resolvers are safe; never expose 5xx internals.
        return $exception->getStatusCode() < 500 && '' !== $exception->getMessage() ? $exception->getMessage() : null;
    }
}
