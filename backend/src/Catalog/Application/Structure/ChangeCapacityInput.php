<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Structure;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of `POST /events/{id}/capacity` (CAP-06): sets the limit of the event, one activity or one variant; `null` =
 * unlimited. A published event needs a reason, which is audited.
 */
final readonly class ChangeCapacityInput
{
    public function __construct(
        #[Assert\Choice(choices: ['event', 'activity', 'variant'])]
        public string $scope,
        #[Assert\Uuid]
        public ?string $scopeId = null,
        #[Assert\PositiveOrZero]
        public ?int $limit = null,
        #[Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {}
}
