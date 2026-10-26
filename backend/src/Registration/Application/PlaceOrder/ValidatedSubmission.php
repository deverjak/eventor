<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Catalog\Contract\ValidatedAnswerSet;

/**
 * The answers of a request after the server-side validation: the order set and one set per person.
 */
final readonly class ValidatedSubmission
{
    /**
     * @param array<string, ValidatedAnswerSet> $persons keyed by `personRef`
     */
    public function __construct(
        public ValidatedAnswerSet $order,
        public array $persons,
    ) {}
}
