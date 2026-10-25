<?php

declare(strict_types=1);

namespace Eventor\Attention\Infrastructure\Symfony;

use Eventor\Attention\Application\Action\AsAttentionHandler;
use Eventor\Shared\Infrastructure\Symfony\ModuleInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Attention: the "needs a human" queue (ADM-03). Services, the `attention_events` transport and the
 * `attention_item` state machine are declared in services.php; here `#[AsAttentionHandler]` becomes the tag
 * `eventor.attention_handler` (indexed by the item type) that `AttentionHandlers` collects into a service locator.
 */
final class AttentionModule implements ModuleInterface
{
    public const string HANDLER_TAG = 'eventor.attention_handler';

    public function name(): string
    {
        return 'Attention';
    }

    public function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            AsAttentionHandler::class,
            static function (ChildDefinition $definition, AsAttentionHandler $attribute): void {
                $definition->addTag(self::HANDLER_TAG, ['key' => $attribute->type->value]);
            },
        );
    }
}
