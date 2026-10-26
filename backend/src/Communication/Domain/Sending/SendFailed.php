<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Sending;

/**
 * The channel did not take the message (SMTP down, refused). `$code` is a stable, address-free code: `smtp_550`,
 * `transport_error`. Retryable by the Messenger retry strategy of `async_mail`.
 */
final class SendFailed extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, ?\Throwable $previous = null)
    {
        parent::__construct('The message was not sent: '.$errorCode, 0, $previous);
    }
}
