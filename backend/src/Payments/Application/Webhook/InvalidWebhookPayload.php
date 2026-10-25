<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Webhook;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * A correctly signed webhook with a body Eventor cannot read (not JSON, no id or type). 400: Pairrot does not retry it.
 */
#[AsProblem(code: 'invalid_webhook', status: 400)]
final class InvalidWebhookPayload extends DomainException
{
    public static function create(string $why): self
    {
        return new self('The webhook body is not a Pairrot event: '.$why.'.');
    }
}
