<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Answer;

use Eventor\Shared\Domain\Id\OrderId;

/**
 * Answer sets of the active tenant.
 */
interface AnswerSetRepository
{
    /**
     * The current (not superseded) set of a subject, if any.
     */
    public function current(string $scope, string $subjectId): ?AnswerSet;

    /**
     * @return list<AnswerSet> every current set of the order
     */
    public function currentOfOrder(OrderId $order): array;

    public function add(AnswerSet $set): void;

    /**
     * A correction: marks `$old` as superseded by `$new` and adds `$new`. The old set is released before the new one is
     * inserted (one current set per subject), and the new row is inserted at the next flush.
     */
    public function replace(AnswerSet $old, AnswerSet $new): void;
}
