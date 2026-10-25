<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

/**
 * Organizer policy for an overpaid order (chapter 03, `organizer_policy.overpayment_handling`).
 */
enum OverpaymentHandling: string
{
    case Refund = 'refund';
    case Donation = 'donation';
    case Ask = 'ask';
}
