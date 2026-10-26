<?php

declare(strict_types=1);

namespace Eventor\Registration\Domain\Answer;

use Eventor\Shared\Domain\Id\AbstractId;

/**
 * Module-internal id of an answer set (it never crosses the module boundary, so it is not in Shared).
 */
final readonly class AnswerSetId extends AbstractId {}
