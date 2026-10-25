<?php

declare(strict_types=1);

namespace Eventor\Tests\Fixtures\Routing\NoAttribute\Contract\Event;

/** Lives in a Contract\Event namespace but forgot #[IntegrationEvent]. */
final readonly class ForgotAttribute {}
