<?php

declare(strict_types=1);

namespace Eventor\Content\UserInterface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Whether the item belongs to the public archive of the finished event (POST-07).
 */
final readonly class ArchiveRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?bool $inArchive = null,
    ) {}
}
