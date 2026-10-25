<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain;

use Doctrine\ORM\Mapping as ORM;
use Eventor\Shared\Domain\Exception\InvalidMoney;

/**
 * Immutable money in minor units (haléře). There is no float anywhere: amounts are integers, percentages are
 * basis points, rounding is half-up on the line (chapter 08, "Peníze"). Persist it as an embeddable:
 *
 *     #[ORM\Embedded(class: Money::class, columnPrefix: 'amount_')]   // amount_minor + amount_currency
 */
#[ORM\Embeddable]
final readonly class Money implements \JsonSerializable
{
    public function __construct(
        #[ORM\Column(name: 'minor', type: 'bigint')]
        public int $minor,
        #[ORM\Column(name: 'currency', type: 'string', length: 3, enumType: Currency::class)]
        public Currency $currency,
    ) {}

    public static function of(int $minor, Currency $currency = Currency::CZK): self
    {
        return new self($minor, $currency);
    }

    public static function zero(Currency $currency = Currency::CZK): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->checked($this->minor, $other->minor), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->checked($this->minor, -$other->minor), $this->currency);
    }

    public function multiply(int $factor): self
    {
        return new self($this->exact($this->minor * $factor), $this->currency);
    }

    /**
     * Percentage in basis points (1 % = 100 bp), rounded half-up (away from zero) to whole minor units.
     */
    public function percentage(int $basisPoints): self
    {
        $scaled = $this->exact($this->minor * $basisPoints);

        $quotient = intdiv(abs($scaled) + 5_000, 10_000);

        return new self($scaled < 0 ? -$quotient : $quotient, $this->currency);
    }

    /**
     * Splits the amount by the given ratios without losing a single minor unit (largest remainder method).
     *
     * @param non-empty-list<int> $ratios
     *
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        if ([] === $ratios) {
            throw InvalidMoney::invalidRatios();
        }
        foreach ($ratios as $ratio) {
            if ($ratio <= 0) {
                throw InvalidMoney::invalidRatios();
            }
        }

        $total = array_sum($ratios);
        $sign = $this->minor < 0 ? -1 : 1;
        $absolute = abs($this->minor);

        $shares = [];
        $remainders = [];
        $allocated = 0;
        foreach ($ratios as $index => $ratio) {
            $share = intdiv($absolute * $ratio, $total);
            $shares[$index] = $share;
            $remainders[$index] = ($absolute * $ratio) % $total;
            $allocated += $share;
        }

        $leftover = $absolute - $allocated;
        // Largest remainders first; earlier index wins ties so the result is deterministic.
        uksort($remainders, static fn(int $a, int $b): int => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach (array_keys($remainders) as $index) {
            if ($leftover <= 0) {
                break;
            }
            ++$shares[$index];
            --$leftover;
        }

        ksort($shares);

        return array_map(fn(int $share): self => new self($sign * $share, $this->currency), array_values($shares));
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function isZero(): bool
    {
        return 0 === $this->minor;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    /**
     * @return array{minor: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency->value];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidMoney::currencyMismatch($this->currency->value, $other->currency->value);
        }
    }

    private function checked(int $a, int $b): int
    {
        return $this->exact($a + $b);
    }

    /**
     * PHP turns an overflowing integer operation into a float; money must fail loudly instead.
     */
    private function exact(int|float $result): int
    {
        if (!\is_int($result)) {
            throw InvalidMoney::overflow();
        }

        return $result;
    }
}
