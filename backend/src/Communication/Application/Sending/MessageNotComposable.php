<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Sending;

/**
 * The message cannot be written at all: the order, the event or the template it refers to is gone or broken. Not
 * retried (the same attempt would fail the same way); the delivery fails with `$errorCode`.
 */
final class MessageNotComposable extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct('The message cannot be composed: '.$errorCode, 0, $previous);
    }
}
