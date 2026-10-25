<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Exception;

#[AsProblem(code: 'invalid_money', status: 422)]
final class InvalidMoney extends DomainException
{
    public static function currencyMismatch(string $left, string $right): self
    {
        return new self(\sprintf('Cannot combine money in %s with money in %s.', $left, $right));
    }

    public static function overflow(): self
    {
        return new self('Money amount exceeds the supported range.');
    }

    public static function invalidRatios(): self
    {
        return new self('Allocation ratios must be positive integers.');
    }
}
