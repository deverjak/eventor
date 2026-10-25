<?php

declare(strict_types=1);

namespace Eventor\Identity\Domain\DeviceToken;

use Eventor\Shared\Domain\Id\AbstractId;

/**
 * Module-internal id of a check-in device token (the id is also the public part of the token `dev1_<id>_<secret>`).
 */
final readonly class DeviceTokenId extends AbstractId {}
