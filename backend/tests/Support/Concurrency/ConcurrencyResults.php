<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Concurrency;

/**
 * The outcomes of one ConcurrencyHarness run, in the order the processes were started.
 */
final readonly class ConcurrencyResults
{
    /**
     * @param list<ProcessOutcome> $outcomes
     */
    public function __construct(public array $outcomes) {}

    /**
     * @return list<ProcessOutcome>
     */
    public function succeeded(): array
    {
        return array_values(array_filter($this->outcomes, static fn(ProcessOutcome $o): bool => $o->succeeded()));
    }

    /**
     * @return list<ProcessOutcome>
     */
    public function failedWith(string $code): array
    {
        return array_values(array_filter($this->outcomes, static fn(ProcessOutcome $o): bool => !$o->succeeded() && $code === $o->code()));
    }

    /**
     * Everything that is neither a success nor a business failure the test expects: crashes, deadlocks, SQL errors.
     *
     * @param list<string> $expectedCodes
     *
     * @return list<ProcessOutcome>
     */
    public function unexpected(array $expectedCodes): array
    {
        return array_values(array_filter($this->outcomes, static fn(ProcessOutcome $o): bool => !$o->succeeded() && !\in_array($o->code(), $expectedCodes, true)));
    }

    public function describe(): string
    {
        return implode("\n", array_map(static fn(ProcessOutcome $o): string => $o->describe(), $this->outcomes));
    }
}
