<?php

declare(strict_types=1);

namespace Eventor\Payments\Domain\Refund;

use Eventor\Shared\Domain\Id\OrderId;
use Eventor\Shared\Domain\Id\RefundTaskId;

interface RefundTaskRepository
{
    public function add(RefundTask $task): void;

    public function find(RefundTaskId $id): ?RefundTask;

    public function lock(RefundTaskId $id): ?RefundTask;

    public function findOpenForOrder(OrderId $order): ?RefundTask;

    public function hasOpenForOrder(OrderId $order): bool;
}
