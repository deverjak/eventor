<?php

declare(strict_types=1);

namespace Eventor\Payments\Contract;

/**
 * Why Pairrot could not allocate a bank transaction (`matching.result`).
 */
enum MatchingResult: string
{
    case MissingVariableSymbol = 'missing_variable_symbol';
    case RequestNotFound = 'request_not_found';
    case AmountMismatch = 'amount_mismatch';
    case RequestAlreadyPaid = 'request_already_paid';
    case RequestCancelled = 'request_cancelled';
}
