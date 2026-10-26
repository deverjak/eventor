<?php

declare(strict_types=1);

namespace Eventor\Content\Domain\Material;

use Eventor\Shared\Domain\Id\MaterialId;

interface MaterialRepository
{
    public function find(MaterialId $id): ?Material;

    public function add(Material $material): void;
}
