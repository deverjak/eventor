<?php

declare(strict_types=1);

namespace Eventor\Legal\UserInterface\Http\Request;

final readonly class PublishVersionRequest
{
    public function __construct(public bool $requiresReconfirmation = false) {}
}
