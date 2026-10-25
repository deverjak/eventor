<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Port\Exception;

/**
 * 409 `external_id_conflict` / `variable_symbol_conflict`: the same external id already exists with another body.
 * A bug in Eventor, not a state of the domain: dead letter and alert, never a new attempt with the same id.
 */
final class PaymentRequestConflict extends PaymentGatewayException
{
    public function __construct(private readonly string $conflict)
    {
        parent::__construct('Pairrot reports a conflict: '.$conflict);
    }

    public function errorCode(): string
    {
        return $this->conflict;
    }
}
