<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Registration\Domain;

use Eventor\Registration\Application\Correct\AnswerDiff;
use Eventor\Registration\Application\PlaceOrder\FormTimeTrap;
use Eventor\Registration\Application\SelfService\SelfServiceTokens;
use Eventor\Registration\Contract\Exception\NotEligible;
use Eventor\Registration\Domain\Eligibility\ParticipationEligibilityPolicy;
use Eventor\Registration\Domain\Exception\SpamSuspected;
use Eventor\Registration\Domain\Pricing\PriceCalculator;
use Eventor\Shared\Domain\Currency;
use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Id\SelfServiceTokenId;
use Eventor\Shared\Domain\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Small pure rules of the order: age conditions, price, the antispam, the self-service token, the answer diff.
 */
final class RegistrationRulesTest extends TestCase
{
    public function test_the_age_is_judged_at_the_reference_date(): void
    {
        $reference = new \DateTimeImmutable('2026-11-21');
        ParticipationEligibilityPolicy::assertAge(new \DateTimeImmutable('2011-11-21'), $reference, 15, null, 'Trail 10 km');
        $this->addToAssertionCount(1);

        $this->expectException(NotEligible::class);
        ParticipationEligibilityPolicy::assertAge(new \DateTimeImmutable('2011-11-22'), $reference, 15, null, 'Trail 10 km');
    }

    public function test_a_limit_without_a_birth_date_is_not_eligible_and_without_a_limit_anything_goes(): void
    {
        ParticipationEligibilityPolicy::assertAge(null, new \DateTimeImmutable('2026-11-21'), null, null, 'Open');
        $this->addToAssertionCount(1);

        $this->expectException(NotEligible::class);
        ParticipationEligibilityPolicy::assertAge(null, new \DateTimeImmutable('2026-11-21'), null, 14, 'Kids');
    }

    public function test_minors_are_under_eighteen_at_the_reference_date(): void
    {
        $reference = new \DateTimeImmutable('2026-11-21');

        self::assertTrue(ParticipationEligibilityPolicy::isMinor(new \DateTimeImmutable('2008-11-22'), $reference));
        self::assertFalse(ParticipationEligibilityPolicy::isMinor(new \DateTimeImmutable('2008-11-21'), $reference));
        self::assertFalse(ParticipationEligibilityPolicy::isMinor(null, $reference));
    }

    public function test_the_total_is_the_sum_of_the_lines(): void
    {
        self::assertSame(50000, PriceCalculator::total([Money::of(30000), Money::of(10000), Money::of(10000)], Currency::CZK)->minor);
        self::assertTrue(PriceCalculator::total([], Currency::CZK)->isZero());
    }

    public function test_the_honeypot_and_the_minimum_fill_time(): void
    {
        $clock = new MockClock('2026-11-01 12:00:00');
        $trap = new FormTimeTrap('secret', $clock);
        $token = $trap->issue();

        $trap->assertHuman('', null);
        $trap->assertHuman(null, '');
        $clock->sleep(4);
        $trap->assertHuman('', $token);
        $this->addToAssertionCount(3);

        foreach ([['x', null], ['', '1.deadbeef'], ['', 'garbage']] as [$honeypot, $forged]) {
            try {
                $trap->assertHuman($honeypot, $forged);
                self::fail('Expected the form to be refused.');
            } catch (SpamSuspected) {
                $this->addToAssertionCount(1);
            }
        }

        $fresh = new FormTimeTrap('secret', $clock);
        $quick = $fresh->issue();
        $this->expectException(SpamSuspected::class);
        $fresh->assertHuman('', $quick);
    }

    public function test_the_self_service_token_is_derived_parsed_and_hashed(): void
    {
        $tokens = new SelfServiceTokens('app-secret', 'https://eventor.test/', new MockClock('2026-11-01 12:00:00'));
        $id = SelfServiceTokenId::generate();

        $token = $tokens->tokenFor($id);

        self::assertSame($token, $tokens->tokenFor($id), 'derivable: the same id gives the same link');
        self::assertStringStartsWith('https://eventor.test/s/ss1_', $tokens->urlFor($id));
        $parsed = SelfServiceTokens::parse($token);
        self::assertNotNull($parsed);
        self::assertTrue($parsed['id']->equals($id));
        self::assertTrue($tokens->matches($parsed['secret'], $tokens->hashOf($tokens->secretFor($id))));
        self::assertFalse($tokens->matches('x'.substr($parsed['secret'], 1), $tokens->hashOf($tokens->secretFor($id))));
        self::assertNotSame($token, (new SelfServiceTokens('other-secret', 'https://eventor.test', new MockClock()))->tokenFor($id));
        self::assertNull(SelfServiceTokens::parse('ss1_nonsense'));

        $issued = $tokens->issue(OrganizerId::generate(), OrderId::generate(), new \DateTimeImmutable('2026-11-22 16:00:00+00:00'), 'order_placed');
        self::assertSame('2027-02-20', $issued->expiresAt()->format('Y-m-d'), 'the event end plus 90 days');
    }

    public function test_the_answer_diff_names_only_the_changed_fields(): void
    {
        $before = ['first_name' => ['t' => 'short_text', 'v' => 'Jana'], 'tshirt' => ['t' => 'single_choice', 'v' => 'M']];
        $after = ['first_name' => ['t' => 'short_text', 'v' => 'Janička'], 'dog' => ['t' => 'short_text', 'v' => 'Rex']];

        self::assertSame(['dog', 'first_name', 'tshirt'], AnswerDiff::changedKeys($before, $after));
        self::assertSame([], AnswerDiff::changedKeys($before, $before));
    }
}
