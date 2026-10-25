<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

use Eventor\Shared\Domain\Id\RefundTaskId;

final class RefundTaskIdType extends AbstractIdType
{
    public const string NAME = 'refund_task_id';

    protected function idClass(): string
    {
        return RefundTaskId::class;
    }
}
