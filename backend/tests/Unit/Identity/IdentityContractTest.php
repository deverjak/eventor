<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Contract\OrganizerPaymentSettings;
use Eventor\Shared\Domain\Id\OrganizerId;
use PHPUnit\Framework\TestCase;

final class IdentityContractTest extends TestCase
{
    public function test_transfer_payments_need_the_subtenant_the_bank_connection_and_the_switch(): void
    {
        $organizer = OrganizerId::generate();

        self::assertTrue((new OrganizerPaymentSettings($organizer, 'sub_1', 'bc_1', 'CZ65****0100', true, true))->canIssueTransferPayments());
        self::assertFalse((new OrganizerPaymentSettings($organizer, 'sub_1', 'bc_1', 'CZ65****0100', false, true))->canIssueTransferPayments());
        self::assertFalse((new OrganizerPaymentSettings($organizer, 'sub_1', null, null, true, true))->canIssueTransferPayments());
        self::assertFalse((new OrganizerPaymentSettings($organizer, null, null, null, true, false))->canIssueTransferPayments());
    }
}
