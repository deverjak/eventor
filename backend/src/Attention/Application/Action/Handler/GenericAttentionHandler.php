<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action\Handler;

use Eventor\Attention\Application\Action\AbstractAttentionHandler;

/**
 * Types without a dedicated handler (pairrot_review, cancellation_request, ...) offer acknowledge, resolve and
 * dismiss. Not tagged: `AttentionHandlers` falls back to it.
 */
final class GenericAttentionHandler extends AbstractAttentionHandler {}
