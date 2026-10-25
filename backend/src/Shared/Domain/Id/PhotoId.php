<?php

declare(strict_types=1);

namespace Eventor\Shared\Domain\Id;

/**
 * Owned by Content. It lives in Shared because it crosses a module boundary: other modules store it in their
 * domain model or carry it in Contract DTOs and events, and a Domain may depend on Shared only.
 */
final readonly class PhotoId extends AbstractId {}
