<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Registration\Capacity;

use Eventor\Registration\Domain\Capacity\ClaimKind;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class VerifyCapacityCommandTest extends CapacityTestCase
{
    private function verify(): CommandTester
    {
        $application = new Application(self::$kernel ?? throw new \LogicException('Kernel not booted.'));
        $tester = new CommandTester($application->find('eventor:capacity:verify'));
        $tester->execute([]);

        return $tester;
    }

    public function test_consistent_counters_pass(): void
    {
        $bucket = $this->bucket(10);
        $this->claim([$bucket], 2);
        $confirmed = $this->claim([$bucket], 3, ClaimKind::Confirmed);
        $this->allocator()->release($this->organizer, [$confirmed]);

        $tester = $this->verify();

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('match the claims', $tester->getDisplay());
    }

    public function test_it_reports_a_bucket_whose_counters_were_changed_behind_the_allocators_back(): void
    {
        $good = $this->bucket(10);
        $drifted = $this->bucket(10);
        $this->claim([$good]);
        $this->claim([$drifted], 2);
        $this->connection()->executeStatement('UPDATE registration.capacity_bucket SET held = 5, confirmed = 1 WHERE id = ?', [$drifted->toString()]);

        $tester = $this->verify();

        self::assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString($drifted->toString(), $display);
        self::assertStringNotContainsString($good->toString(), $display);
        self::assertStringContainsString('1 bucket(s) drifted', $display);
    }

    public function test_a_bucket_with_a_claim_but_zero_counters_is_drift_too(): void
    {
        $bucket = $this->bucket(10);
        $this->claim([$bucket], 2, ClaimKind::Confirmed);
        $this->connection()->executeStatement('UPDATE registration.capacity_bucket SET confirmed = 0 WHERE id = ?', [$bucket->toString()]);

        self::assertSame(1, $this->verify()->getStatusCode());
    }

    public function test_no_buckets_at_all_is_consistent(): void
    {
        self::assertSame(0, $this->verify()->getStatusCode());
    }
}
