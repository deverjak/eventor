<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\BankTransactionId;

final class BankTransactionIdType extends AbstractIdType
{
    public const string NAME = 'bank_transaction_id';

    protected function idClass(): string
    {
        return BankTransactionId::class;
    }
}
