<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Suppression;

use Eventor\Communication\Contract\SuppressionScope;

interface SuppressionRepository
{
    public function add(Suppression $suppression): void;

    public function remove(Suppression $suppression): void;

    public function find(SuppressionId $id): ?Suppression;

    public function findByHash(string $emailSha256, SuppressionScope $scope): ?Suppression;
}
