<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Attention\Application\Action\AttentionActionRunner;
use Eventor\Attention\Application\Query\AttentionItemQuery;
use Eventor\Attention\Contract\AttentionQueue;
use Eventor\Attention\Domain\AttentionItem;
use Eventor\Shared\Application\Attention\AttentionRaiser;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Attention', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'attention');

    // open -> acknowledged -> resolved | dismissed (chapter 05, "Výjimka"). Resolved/dismissed items are final;
    // the same cause may open a new item afterwards (partial unique index on the unresolved dedupe key).
    $container->extension('framework', ['workflows' => ['attention_item' => [
        'type' => 'state_machine',
        'marking_store' => ['type' => 'method', 'property' => 'state'],
        'supports' => [AttentionItem::class],
        'initial_marking' => 'open',
        'places' => ['open', 'acknowledged', 'resolved', 'dismissed'],
        'transitions' => [
            'acknowledge' => ['from' => 'open', 'to' => 'acknowledged'],
            'resolve' => ['from' => ['open', 'acknowledged'], 'to' => 'resolved'],
            'dismiss' => ['from' => ['open', 'acknowledged'], 'to' => 'dismissed'],
        ],
    ]]]);

    if ('test' === $container->env()) {
        foreach ([AttentionQueue::class, AttentionItemQuery::class, AttentionActionRunner::class, AttentionRaiser::class] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
    }
};
