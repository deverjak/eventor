<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract\Exception;

use Eventor\Shared\Domain\Exception\AsProblem;
use Eventor\Shared\Domain\Exception\DomainException;

/**
 * The self-service token is missing, malformed, wrong, expired or revoked (API-04). Expired and revoked tokens point
 * to the link recovery (REG-11).
 */
#[AsProblem(code: 'token_invalid', status: 401)]
final class SelfServiceTokenRejected extends DomainException
{
    public static function missing(): self
    {
        return new self('The self-service token is missing or malformed.');
    }

    public static function unknown(): self
    {
        return new self('The self-service token is not valid.');
    }

    public static function expired(): self
    {
        return new self('The self-service link has expired or was revoked. Request a new one.');
    }

    /**
     * @return list<array{action: string, href?: string}>
     */
    public function nextSteps(): array
    {
        return [['action' => 'recover_self_service_link', 'href' => '/api/public/self-service/recover']];
    }
}
