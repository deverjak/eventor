<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'invalid_policy_value', status: 422)]
final class InvalidPolicyValue extends DomainException
{
    private function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function of(string $field, string $allowed): self
    {
        return new self(\sprintf('%s must be %s.', $field, $allowed), $field);
    }

    public function context(): array
    {
        return ['field' => $this->field];
    }
}
