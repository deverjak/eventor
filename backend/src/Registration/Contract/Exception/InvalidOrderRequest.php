<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;
use Eventor\Shared\Domain\Exception\ProvidesViolations;

/**
 * The structure of the order request is wrong: an item that is not in the offer, a person without an item, a decision
 * for a requirement that does not apply, a minor without a guardian. The answers themselves are judged by the form
 * (`form_invalid`), the missing mandatory confirmations by Legal (`missing_confirmation`).
 */
#[AsProblem(code: 'invalid_order_request', status: 422)]
final class InvalidOrderRequest extends DomainException implements ProvidesViolations
{
    /**
     * @param list<array{path: string, message: string, code: string}> $violations
     */
    public function __construct(private readonly array $violations)
    {
        parent::__construct(\sprintf('%d problem(s) in the order request.', \count($violations)));
    }

    public static function at(string $path, string $code, string $message): self
    {
        return new self([['path' => $path, 'message' => $message, 'code' => $code]]);
    }

    /**
     * @return list<array{path: string, message: string, code: string}>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function context(): array
    {
        return ['violationCount' => \count($this->violations)];
    }
}
