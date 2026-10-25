<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\Handlers;

use Eventor\Tests\Fixtures\Routing\Happy\GoodEvent;

/** Handler class of GoodEvent; wired by tag in the test, not a real service. */
final class GoodSubscriber
{
    public function __invoke(GoodEvent $event): void {}
}
