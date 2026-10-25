<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action;

use Eventor\Attention\Application\Action\Handler\GenericAttentionHandler;
use Eventor\Shared\Application\Attention\AttentionType;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The handlers tagged by `#[AsAttentionHandler]`, looked up by item type (chapter 09: an open extension point
 * instead of a `switch` over the type).
 */
final readonly class AttentionHandlers
{
    /**
     * @param ServiceLocator<AttentionHandler> $handlers
     */
    public function __construct(
        #[AutowireLocator('eventor.attention_handler', indexAttribute: 'key')]
        private ServiceLocator $handlers,
        private GenericAttentionHandler $fallback,
    ) {}

    public function for(AttentionType $type): AttentionHandler
    {
        return $this->handlers->has($type->value) ? $this->handlers->get($type->value) : $this->fallback;
    }
}
