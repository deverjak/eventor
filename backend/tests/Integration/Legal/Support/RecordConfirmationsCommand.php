<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Legal\Support;

use Eventor\Legal\Contract\ConfirmationToRecord;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Test command that plays the role of the callers of `ConfirmationRecorder` (Registration's PlaceOrder, CheckIn): a
 * handler on `command.bus` that records the given decisions inside the command transaction.
 */
final readonly class RecordConfirmationsCommand
{
    /**
     * @param list<ConfirmationToRecord> $confirmations
     */
    public function __construct(
        public OrganizerId $organizerId,
        public array $confirmations,
    ) {}
}
