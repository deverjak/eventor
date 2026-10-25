<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Catalog;

use Eventor\Catalog\Application\Publish\EventPublishabilityChecker;
use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Application\Publish\Rule\FormHasIdentityFields;
use Eventor\Catalog\Application\Publish\Rule\HasActivityWithVariant;
use Eventor\Catalog\Application\Publish\Rule\MinorsOnlyNeedsGuardianFields;
use Eventor\Catalog\Application\Publish\Rule\PaidVariantNeedsPaymentMethod;
use Eventor\Catalog\Application\Publish\Rule\TransferNeedsPairrot;
use Eventor\Catalog\Domain\Exception\EventNotPublishable;
use Eventor\Catalog\Domain\Form\DefaultFormSchema;
use Eventor\Catalog\Domain\Form\FormSchema;
use Eventor\Catalog\Domain\Structure\PaymentRules;
use Eventor\Identity\Contract\OrganizerPaymentSettings;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Shared\Domain\Id\ActivityId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Payment\PaymentMethod;
use Eventor\Tests\Support\Catalog\Build;
use PHPUnit\Framework\TestCase;

/**
 * Chapter 04, path 1: the publication rules, one by one and together.
 */
final class PublishRulesTest extends TestCase
{
    public function test_an_event_without_activities_is_not_publishable(): void
    {
        $candidate = new PublishCandidate(Build::event(), [], [], DefaultFormSchema::create());

        self::assertSame(['no_activity'], array_column((new HasActivityWithVariant())->check($candidate), 'code'));
    }

    public function test_an_activity_without_a_variant_is_named(): void
    {
        $event = Build::event();
        $withVariant = Build::activity($event);
        $without = Build::activity($event, 'Canicross');
        $candidate = new PublishCandidate($event, [$withVariant, $without], [Build::variant($event, $withVariant)], DefaultFormSchema::create());

        $violations = (new HasActivityWithVariant())->check($candidate);

        self::assertCount(1, $violations);
        self::assertSame('activities.'.$without->id()->toString(), $violations[0]['path']);
        self::assertStringContainsString('Canicross', $violations[0]['message']);
    }

    public function test_a_paid_variant_needs_a_payment_method_but_a_free_one_does_not(): void
    {
        $paid = $this->candidate(price: 30000);
        $free = $this->candidate(price: 0);
        $cash = $this->candidate(price: 30000, rules: new PaymentRules([PaymentMethod::Cash], 7, 48, false, true, null));

        self::assertSame(['payment_method_required'], array_column((new PaidVariantNeedsPaymentMethod())->check($paid), 'code'));
        self::assertSame([], (new PaidVariantNeedsPaymentMethod())->check($free));
        self::assertSame([], (new PaidVariantNeedsPaymentMethod())->check($cash));
    }

    public function test_a_transfer_needs_a_connected_pairrot_account_and_fails_closed_without_the_facade(): void
    {
        $organizer = OrganizerId::generate();
        $transfer = $this->candidate(price: 30000, rules: new PaymentRules([PaymentMethod::BankTransfer], 7, 48, false, true, null), organizer: $organizer);
        $cashOnly = $this->candidate(price: 30000, rules: new PaymentRules([PaymentMethod::Cash], 7, 48, false, true, null));

        self::assertSame(['transfer_requires_pairrot'], array_column((new TransferNeedsPairrot($this->settings($organizer, connected: false)))->check($transfer), 'code'));
        self::assertSame(['transfer_requires_pairrot'], array_column((new TransferNeedsPairrot())->check($transfer), 'code'));
        self::assertSame([], (new TransferNeedsPairrot($this->settings($organizer, connected: true)))->check($transfer));
        self::assertSame([], (new TransferNeedsPairrot())->check($cashOnly));
    }

    public function test_an_activity_for_minors_needs_the_guardian_fields(): void
    {
        $event = Build::event();
        $kids = Build::activity($event, 'Dětský závod', minorsOnly: true);
        $without = new PublishCandidate($event, [$kids], [Build::variant($event, $kids)], DefaultFormSchema::create());
        $with = new PublishCandidate($event, [$kids], [Build::variant($event, $kids)], FormSchema::fromArray(['fields' => [
            ['key' => 'g_first', 'type' => 'short_text', 'label' => 'Jméno zástupce', 'scope' => 'person', 'semantic' => 'guardian_first_name', 'required' => true],
            ['key' => 'g_last', 'type' => 'short_text', 'label' => 'Příjmení zástupce', 'scope' => 'person', 'semantic' => 'guardian_last_name', 'required' => true],
            ['key' => 'g_mail', 'type' => 'email', 'label' => 'E-mail zástupce', 'scope' => 'person', 'semantic' => 'guardian_email', 'required' => true],
        ]]));
        $boundElsewhere = new PublishCandidate($event, [$kids], [Build::variant($event, $kids)], FormSchema::fromArray(['fields' => [
            ['key' => 'g_first', 'type' => 'short_text', 'label' => 'Jméno zástupce', 'scope' => 'person', 'semantic' => 'guardian_first_name', 'activityIds' => [ActivityId::generate()->toString()]],
        ]]));

        self::assertCount(3, (new MinorsOnlyNeedsGuardianFields())->check($without));
        self::assertSame([], (new MinorsOnlyNeedsGuardianFields())->check($with));
        self::assertCount(3, (new MinorsOnlyNeedsGuardianFields())->check($boundElsewhere), 'a guardian field bound to another activity does not count');
    }

    public function test_the_form_must_ask_every_participant_for_first_and_last_name(): void
    {
        $event = Build::event();
        $candidate = new PublishCandidate($event, [], [], FormSchema::fromArray(['fields' => [
            ['key' => 'email', 'type' => 'email', 'label' => 'E-mail', 'scope' => 'order', 'semantic' => 'email', 'required' => true],
        ]]));

        self::assertSame(['identity_field_missing', 'identity_field_missing'], array_column((new FormHasIdentityFields())->check($candidate), 'code'));
        self::assertSame([], (new FormHasIdentityFields())->check(new PublishCandidate($event, [], [], DefaultFormSchema::create())));
    }

    public function test_the_checker_reports_every_broken_rule_at_once(): void
    {
        $checker = new EventPublishabilityChecker([new HasActivityWithVariant(), new FormHasIdentityFields(), new PaidVariantNeedsPaymentMethod()]);

        try {
            $checker->assertPublishable(new PublishCandidate(Build::event(), [], [], FormSchema::empty()));
            self::fail('Nothing is configured.');
        } catch (EventNotPublishable $e) {
            self::assertSame(['no_activity', 'identity_field_missing', 'identity_field_missing'], array_column($e->violations(), 'code'));
            self::assertSame(['violationCount' => 3], $e->context());
        }
    }

    public function test_a_complete_event_passes_the_checker(): void
    {
        $checker = new EventPublishabilityChecker([new HasActivityWithVariant(), new FormHasIdentityFields(), new PaidVariantNeedsPaymentMethod()]);

        $checker->assertPublishable($this->candidate(price: 0));

        $this->addToAssertionCount(1);
    }

    private function candidate(int $price, ?PaymentRules $rules = null, ?OrganizerId $organizer = null): PublishCandidate
    {
        $event = Build::event($organizer, rules: $rules);
        $activity = Build::activity($event);

        return new PublishCandidate($event, [$activity], [Build::variant($event, $activity, $price)], DefaultFormSchema::create());
    }

    private function settings(OrganizerId $organizer, bool $connected): OrganizerSettings
    {
        $settings = self::createStub(OrganizerSettings::class);
        $settings->method('paymentSettings')->willReturn(new OrganizerPaymentSettings($organizer, $connected ? 'sub-1' : null, $connected ? 'conn-1' : null, null, $connected, true));

        return $settings;
    }
}
