<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action;

use Eventor\Shared\Application\Attention\AttentionType;

/**
 * Marks the handler that offers and executes the actions of one item type. `AttentionModule::build()` turns it
 * into the tag `eventor.attention_handler` indexed by the type value; `AttentionHandlers` reads the locator.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsAttentionHandler
{
    public function __construct(public AttentionType $type) {}
}
