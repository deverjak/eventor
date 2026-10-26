<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Id;

/**
 * Owned by Content (a post-event material: results, video, PDF, partner thanks). In Shared like the other ids so that
 * the Doctrine type is registered once and Contract views can carry it.
 */
final readonly class MaterialId extends AbstractId {}
