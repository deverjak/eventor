<?php

declare(strict_types=1);

namespace Eventor\Payments\Infrastructure\Pairrot\Webhook;

/**
 * The webhook secret used when `PAIRROT_WEBHOOK_SECRETS` is not set in a NON-production environment (see the module's
 * services.php), so that tests and the black-box suite can sign webhooks without sharing a secret out of band. It is
 * the published test secret from Pairrot's receiver documentation: public knowledge, worthless in production, where
 * the default is an empty list and every webhook is refused.
 */
final class LocalWebhookSecret
{
    public const string SECRET = 'pwhsec_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8';
    public const string KEY_ID = 'local';
}
