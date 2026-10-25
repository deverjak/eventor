<?php

declare(strict_types=1);

// Titles of RFC 9457 problems (domain "problems", key problem.<code>.title). The machine-readable `code` is the contract
// for clients; the title is for humans.
return [
    'problem.bad_request.title' => 'Neplatný požadavek',
    'problem.unauthorized.title' => 'Je třeba se přihlásit',
    'problem.forbidden.title' => 'Nedostatečné oprávnění',
    'problem.not_found.title' => 'Nenalezeno',
    'problem.method_not_allowed.title' => 'Metoda není povolena',
    'problem.conflict.title' => 'Konflikt',
    'problem.validation_failed.title' => 'Požadavek není platný',
    'problem.rate_limited.title' => 'Příliš mnoho požadavků',
    'problem.internal_error.title' => 'Chyba serveru',
    'problem.service_unavailable.title' => 'Služba je nedostupná',
    'problem.invalid_credentials.title' => 'Nesprávné přihlašovací údaje',
    'problem.csrf_invalid.title' => 'Neplatný CSRF token',
    'problem.idempotency_key_reused.title' => 'Idempotenční klíč už byl použit s jiným požadavkem',
    'problem.idempotency_key_required.title' => 'Chybí idempotenční klíč',
    'problem.invalid_idempotency_key.title' => 'Neplatný idempotenční klíč',
    'problem.request_in_progress.title' => 'Požadavek se stále zpracovává',
    'problem.organizer_slug_taken.title' => 'Adresa organizátora je obsazená',
    'problem.invalid_money.title' => 'Neplatná peněžní částka',
    'problem.domain_rule_violated.title' => 'Porušené pravidlo',
];
