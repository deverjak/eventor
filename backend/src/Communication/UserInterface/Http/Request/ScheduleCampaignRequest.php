<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScheduleCampaignRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public \DateTimeImmutable $at,
    ) {}
}
