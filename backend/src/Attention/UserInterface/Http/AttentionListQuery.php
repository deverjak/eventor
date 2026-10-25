<?php

declare(strict_types=1);

namespace Eventor\Attention\UserInterface\Http;

use Eventor\Attention\Contract\AttentionState;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AttentionListQuery
{
    public function __construct(
        public ?AttentionType $type = null,
        public ?AttentionState $state = null,
        #[Assert\Uuid]
        public ?string $eventId = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $perPage = 25,
    ) {}
}
