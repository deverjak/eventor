<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;
use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Shared\Application\Attention\AttentionType;

/**
 * Informative: an integration is unavailable. A human acknowledges it and resolves it when the integration recovers.
 */
#[AsAttentionHandler(AttentionType::IntegrationDegraded)]
final class IntegrationDegradedHandler extends AbstractAttentionHandler {}
