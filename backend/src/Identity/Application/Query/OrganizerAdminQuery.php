<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Application\Query\View\OrganizerProfileView;
use Eventor\Identity\Application\Query\View\PaymentSettingsView;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read models of the organizer settings pages (profile, payment settings). Every method takes the OrganizerId
 * explicitly: DBAL queries are not covered by the Doctrine tenant filter (ADR 005).
 */
final readonly class OrganizerAdminQuery
{
    public function __construct(
        private Connection $connection,
        private OrganizerSettings $settings,
    ) {}

    public function profile(OrganizerId $organizer): OrganizerProfileView
    {
        /** @var array<string, string|null>|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT id, slug, name, contact_email, contact_phone, legal_name, company_id, vat_id, billing_street, billing_city,
                    billing_postal_code, billing_country, logo_url, cover_image_url, brand_color
               FROM identity.organizer WHERE id = :id',
            ['id' => $organizer->toString()],
        );
        if (false === $row) {
            throw new \LogicException('Organizer vanished during the request.');
        }

        return new OrganizerProfileView(
            (string) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            $row['contact_email'],
            $row['contact_phone'],
            $row['legal_name'],
            $row['company_id'],
            $row['vat_id'],
            $row['billing_street'],
            $row['billing_city'],
            $row['billing_postal_code'],
            $row['billing_country'],
            $row['logo_url'],
            $row['cover_image_url'],
            $row['brand_color'],
        );
    }

    public function paymentSettings(OrganizerId $organizer): PaymentSettingsView
    {
        $policy = $this->settings->policy($organizer);
        $payment = $this->settings->paymentSettings($organizer);

        return new PaymentSettingsView(
            $policy->defaultDueDays,
            $policy->defaultHoldTtlHours,
            $policy->autoRestoreLatePayment,
            $policy->overpaymentHandling->value,
            $policy->checkInMayRecordCash,
            $policy->eventManagerMayResolvePayments,
            null !== $payment->pairrotSubtenantId && null !== $payment->pairrotBankConnectionId,
            $payment->maskedIban,
            $payment->transferEnabled,
            $payment->autoMatching,
        );
    }
}
