<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure;

use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Payments\Application\Port\OrganizerPaymentPolicy;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Reads the organizer policy from Identity. While Identity has not published `OrganizerSettings` (parallel build),
 * the documented defaults of chapter 03 apply: refund overpayments, cash at check-in off, due in 7 days.
 */
#[AsAlias(OrganizerPaymentPolicy::class)]
final readonly class IdentityOrganizerPaymentPolicy implements OrganizerPaymentPolicy
{
    public function __construct(private ?OrganizerSettings $settings = null) {}

    public function checkInMayRecordCash(OrganizerId $organizer): bool
    {
        return $this->settings?->policy($organizer)->checkInMayRecordCash ?? false;
    }

    public function overpaymentHandling(OrganizerId $organizer): OverpaymentHandling
    {
        return $this->settings?->policy($organizer)->overpaymentHandling ?? OverpaymentHandling::Refund;
    }

    public function defaultDueDays(OrganizerId $organizer): int
    {
        return $this->settings?->policy($organizer)->defaultDueDays ?? 7;
    }
}
