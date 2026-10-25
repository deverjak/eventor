<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\PHPStan;

use Eventor\PHPStan\Rules\NoNativeDateTimeRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoNativeDateTimeRule>
 */
final class NoNativeDateTimeRuleTest extends RuleTestCase
{
    private string $checkedPath = '/Fixtures/PHPStan/';

    protected function getRule(): Rule
    {
        return new NoNativeDateTimeRule($this->checkedPath);
    }

    public function test_ambient_time_is_reported_and_parsing_concrete_values_is_not(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/ambient_time.php'], [
            ['new DateTimeImmutable() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', 8],
            ['new DateTime() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', 9],
            ['new DateTimeImmutable() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', 10],
            ['Function time() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', 11],
            ['Function date() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', 12],
            ['Function strtotime() reads ambient time. Inject Psr\Clock\ClockInterface and call $clock->now().', 13],
        ]);
    }

    public function test_only_production_code_is_checked(): void
    {
        $this->checkedPath = '/src/'; // the fixture does not live under src/

        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/ambient_time.php'], []);
    }
}
