<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query\View;

final readonly class SuppressionView
{
    public function __construct(
        public string $id,
        public string $scope,
        public string $reason,
        public \DateTimeImmutable $createdAt,
    ) {}
}
