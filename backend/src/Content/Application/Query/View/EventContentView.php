<?php

declare(strict_types=1);

namespace Eventor\Content\Application\Query\View;

use Eventor\Content\Contract\CollectionView;
use Eventor\Content\Contract\MaterialView;

/**
 * What a page shows of an event: the public page (PUB-06) or the public archive of a finished event (POST-07).
 */
final readonly class EventContentView
{
    /**
     * @param list<CollectionView> $collections
     * @param list<MaterialView>   $materials
     */
    public function __construct(
        public array $collections,
        public array $materials,
    ) {}
}
