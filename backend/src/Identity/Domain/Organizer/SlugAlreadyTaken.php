<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\Organizer;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

#[AsProblem(code: 'organizer_slug_taken', status: 409)]
final class SlugAlreadyTaken extends DomainException
{
    private function __construct(private readonly string $slug)
    {
        parent::__construct(\sprintf('The organizer address "%s" is already taken.', $slug));
    }

    public static function create(string $slug): self
    {
        return new self($slug);
    }

    public function context(): array
    {
        return ['slug' => $this->slug];
    }
}
