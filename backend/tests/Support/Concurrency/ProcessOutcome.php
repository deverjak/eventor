<?php

declare(strict_types=1);

namespace Eventor\Tests\Support\Concurrency;

/**
 * What one subprocess of the harness reported: the exit code and the JSON line it printed last
 * (`{"ok": true}` or `{"ok": false, "code": "capacity_exhausted"}`). A process that crashed prints no JSON and has
 * code `crashed`.
 */
final readonly class ProcessOutcome
{
    /**
     * @param array<string, mixed>|null $report
     */
    public function __construct(
        public int $index,
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public ?array $report,
    ) {}

    public static function fromOutput(int $index, int $exitCode, string $stdout, string $stderr): self
    {
        $report = null;
        $lines = array_values(array_filter(array_map('trim', explode("\n", $stdout)), static fn(string $line): bool => '' !== $line));
        if ([] !== $lines) {
            $decoded = json_decode($lines[\count($lines) - 1], true);
            if (\is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $report = $decoded;
            }
        }

        return new self($index, $exitCode, $stdout, $stderr, $report);
    }

    public function succeeded(): bool
    {
        return 0 === $this->exitCode && true === ($this->report['ok'] ?? null);
    }

    public function code(): string
    {
        if (null === $this->report) {
            return 'crashed';
        }

        $code = $this->report['code'] ?? null;

        return $this->succeeded() ? 'ok' : (\is_string($code) ? $code : 'crashed');
    }

    public function describe(): string
    {
        return \sprintf("#%d exit=%d code=%s\n%s%s", $this->index, $this->exitCode, $this->code(), $this->stdout, $this->stderr);
    }
}
