<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReminderSettingsRequest
{
    /**
     * @param list<int> $paymentOffsetsHours hours relative to the due date of a payment request (negative = before)
     */
    public function __construct(
        #[Assert\Count(max: 5)]
        #[Assert\All(constraints: [new Assert\Type('int'), new Assert\Range(min: -720, max: 720)])]
        public array $paymentOffsetsHours,
        #[Assert\Range(min: 1, max: 336)]
        public int $preEventHours,
    ) {}
}
