<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * The contact person of an event (EVT-01).
 */
final readonly class ContactView
{
    public function __construct(
        public ?string $name,
        public ?string $email,
        public ?string $phone,
    ) {}
}
