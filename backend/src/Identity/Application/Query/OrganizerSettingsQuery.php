<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Identity\Contract\Exception\OrganizerNotFound;
use Eventor\Identity\Contract\OrganizerContact;
use Eventor\Identity\Contract\OrganizerPaymentSettings;
use Eventor\Identity\Contract\OrganizerPolicyView;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Identity\Contract\OverpaymentHandling;
use Eventor\Identity\Domain\Organizer\OrganizerPolicy;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * DBAL read model behind the `OrganizerSettings` facade. An organizer without a policy row (created before the table
 * existed, or by a fixture) reads as the defaults.
 */
#[AsAlias(OrganizerSettings::class)]
final readonly class OrganizerSettingsQuery implements OrganizerSettings
{
    public function __construct(private Connection $connection) {}

    public function paymentSettings(OrganizerId $organizer): OrganizerPaymentSettings
    {
        /** @var array{pairrot_subtenant_id: string|null, pairrot_bank_connection_id: string|null, masked_iban: string|null, transfer_enabled: bool, auto_matching: bool}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT pairrot_subtenant_id, pairrot_bank_connection_id, masked_iban, transfer_enabled, auto_matching FROM identity.organizer WHERE id = :id',
            ['id' => $organizer->toString()],
        );
        if (false === $row) {
            throw OrganizerNotFound::forId($organizer);
        }

        return new OrganizerPaymentSettings(
            $organizer,
            $row['pairrot_subtenant_id'],
            $row['pairrot_bank_connection_id'],
            $row['masked_iban'],
            (bool) $row['transfer_enabled'],
            (bool) $row['auto_matching'],
        );
    }

    public function policy(OrganizerId $organizer): OrganizerPolicyView
    {
        $exists = $this->connection->fetchOne('SELECT 1 FROM identity.organizer WHERE id = :id', ['id' => $organizer->toString()]);
        if (false === $exists) {
            throw OrganizerNotFound::forId($organizer);
        }

        /** @var array{check_in_may_record_cash: bool, event_manager_may_resolve_payments: bool, auto_restore_late_payment: bool, overpayment_handling: string, default_due_days: int|string, default_hold_ttl_hours: int|string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT check_in_may_record_cash, event_manager_may_resolve_payments, auto_restore_late_payment, overpayment_handling, default_due_days, default_hold_ttl_hours
               FROM identity.organizer_policy WHERE organizer_id = :id',
            ['id' => $organizer->toString()],
        );
        if (false === $row) {
            return new OrganizerPolicyView($organizer, false, false, false, OverpaymentHandling::Ask, OrganizerPolicy::DEFAULT_DUE_DAYS, OrganizerPolicy::DEFAULT_HOLD_TTL_HOURS);
        }

        return new OrganizerPolicyView(
            $organizer,
            (bool) $row['check_in_may_record_cash'],
            (bool) $row['event_manager_may_resolve_payments'],
            (bool) $row['auto_restore_late_payment'],
            OverpaymentHandling::from($row['overpayment_handling']),
            (int) $row['default_due_days'],
            (int) $row['default_hold_ttl_hours'],
        );
    }

    public function contact(OrganizerId $organizer): OrganizerContact
    {
        /** @var array{slug: string, name: string, contact_email: string|null}|false $row */
        $row = $this->connection->fetchAssociative('SELECT slug, name, contact_email FROM identity.organizer WHERE id = :id', ['id' => $organizer->toString()]);
        if (false === $row) {
            throw OrganizerNotFound::forId($organizer);
        }

        return new OrganizerContact($organizer, $row['slug'], $row['name'], $row['contact_email'] ?? '');
    }
}
