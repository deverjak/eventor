<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Symfony\DependencyInjection\Compiler;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\NoSubscribers;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Derives the integration-event routing from the subscribers (ADR 003, chapter 09): every handler
 * `#[AsMessageHandler(fromTransport: '<module>_events')]` for an integration event adds that transport to the
 * event's senders in `messenger.senders_locator`, so a publisher never lists its subscribers and a new subscriber
 * cannot be forgotten. The pass fails the container build when
 *  - a class in `<Module>\Contract\Event` lacks `#[IntegrationEvent(name, version)]`, or two events share a name,
 *  - an integration event has no subscriber and is not `#[NoSubscribers]`,
 *  - a handler of an integration event has no `fromTransport`, or its transport is not configured,
 *  - one transport has two handlers of the same event,
 *  - a module handler listens on another module's transport.
 *
 * Extra event sources (tests) are given by the container parameter `eventor.integration_event_sources`
 * (`directory => namespace prefix`).
 */
final readonly class IntegrationEventRoutingPass implements CompilerPassInterface
{
    private const string EVENT_NAMESPACE = '/^Eventor\\\\([A-Za-z0-9]+)\\\\Contract\\\\Event\\\\/';

    private ModuleOf $modules;

    /**
     * @param list<string> $moduleNames names of the application modules (a handler of module X must use `x_events`)
     */
    public function __construct(
        private string $projectDir,
        array $moduleNames = [],
    ) {
        $this->modules = new ModuleOf($moduleNames);
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('messenger.senders_locator')) {
            return;
        }

        $events = $this->discoverEvents($container);

        /** @var array<class-string, array<string, string>> $routing event => [transport => handler service id] */
        $routing = [];
        foreach ($container->findTaggedServiceIds('messenger.message_handler') as $handlerId => $tags) {
            foreach ($tags as $tag) {
                /** @var array<string, mixed> $tag */
                $event = $this->handledClass($container, $handlerId, $tag);
                if (null === $event || !$this->isIntegrationEvent($event)) {
                    continue;
                }

                $transport = $tag['from_transport'] ?? null;
                if (!\is_string($transport) || '' === $transport) {
                    throw new \LogicException(\sprintf('Handler "%s" handles the integration event %s but has no fromTransport. Use #[AsMessageHandler(fromTransport: \'<module>_events\')].', $handlerId, $event));
                }
                if (!$container->hasDefinition('messenger.transport.'.$transport)) {
                    throw new \LogicException(\sprintf('Handler "%s" subscribes %s on transport "%s", which is not configured. Declare it with ModuleServices::eventTransport().', $handlerId, $event, $transport));
                }
                if (isset($routing[$event][$transport])) {
                    throw new \LogicException(\sprintf('Transport "%s" has two handlers of %s ("%s" and "%s"). One handler per event and module; dispatch commands for more reactions.', $transport, $event, $routing[$event][$transport], $handlerId));
                }
                $this->assertOwnTransport($container, $handlerId, $transport);

                $routing[$event][$transport] = $handlerId;
            }
        }

        foreach ($events as $event => $attribute) {
            if (isset($routing[$event])) {
                continue;
            }
            if ([] === (new \ReflectionClass($event))->getAttributes(NoSubscribers::class)) {
                throw new \LogicException(\sprintf('Integration event %s has no subscriber. Add a handler with fromTransport or mark the event #[NoSubscribers].', $event));
            }
        }

        $senders = $container->getDefinition('messenger.senders_locator');
        /** @var array<string, list<string>> $map */
        $map = $senders->getArgument(0);
        foreach ($routing as $event => $transports) {
            $map[$event] = array_values(array_unique([...($map[$event] ?? []), ...array_keys($transports)]));
        }
        $senders->replaceArgument(0, $map);
    }

    /**
     * @return array<class-string, IntegrationEvent> event class => its attribute
     */
    private function discoverEvents(ContainerBuilder $container): array
    {
        $sources = [];
        foreach ($this->glob($this->projectDir.'/src/*/Contract/Event/*.php') as $file) {
            $module = basename(\dirname($file, 3));
            $sources[$file] = \sprintf('Eventor\\%s\\Contract\\Event\\%s', $module, basename($file, '.php'));
            $container->addResource(new FileExistenceResource($file));
        }
        if ($container->hasParameter('eventor.integration_event_sources')) {
            /** @var array<string, string> $extra */
            $extra = $container->getParameterBag()->resolveValue($container->getParameter('eventor.integration_event_sources'));
            foreach ($extra as $directory => $namespace) {
                foreach ($this->glob(rtrim($directory, '/').'/*.php') as $file) {
                    $sources[$file] = rtrim($namespace, '\\').'\\'.basename($file, '.php');
                }
            }
        }

        $events = [];
        $names = [];
        foreach ($sources as $class) {
            if (!class_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }
            $attributes = $reflection->getAttributes(IntegrationEvent::class);
            if ([] === $attributes) {
                throw new \LogicException(\sprintf('%s lives in a Contract\\Event namespace and must carry #[IntegrationEvent(name, version)].', $class));
            }
            $attribute = $attributes[0]->newInstance();
            if (isset($names[$attribute->name])) {
                throw new \LogicException(\sprintf('Integration event name "%s" is used by both %s and %s.', $attribute->name, $names[$attribute->name], $class));
            }
            $names[$attribute->name] = $class;
            $events[$class] = $attribute;
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern): array
    {
        $files = glob($pattern);

        return false === $files ? [] : $files;
    }

    private function isIntegrationEvent(string $class): bool
    {
        if (1 === preg_match(self::EVENT_NAMESPACE, $class)) {
            return true;
        }

        return class_exists($class) && [] !== (new \ReflectionClass($class))->getAttributes(IntegrationEvent::class);
    }

    /**
     * @param array<string, mixed> $tag
     */
    private function handledClass(ContainerBuilder $container, string $handlerId, array $tag): ?string
    {
        if (isset($tag['handles']) && \is_string($tag['handles'])) {
            return $tag['handles'];
        }

        $class = $container->getDefinition($handlerId)->getClass();
        $class = \is_string($class) ? $container->getParameterBag()->resolveValue($class) : null;
        if (!\is_string($class) || !class_exists($class)) {
            return null;
        }

        $method = \is_string($tag['method'] ?? null) ? $tag['method'] : '__invoke';
        $reflection = new \ReflectionClass($class);
        if (!$reflection->hasMethod($method)) {
            return null;
        }

        $parameter = $reflection->getMethod($method)->getParameters()[0] ?? null;
        $type = $parameter?->getType();

        return $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }

    private function assertOwnTransport(ContainerBuilder $container, string $handlerId, string $transport): void
    {
        $class = $container->getDefinition($handlerId)->getClass();
        $module = \is_string($class) ? $this->modules->name($class) : null;
        if (null === $module || 'Shared' === $module) {
            return;
        }

        $expected = strtolower($module).'_events';
        if ($transport !== $expected) {
            throw new \LogicException(\sprintf('Handler "%s" of module %s must subscribe on its own transport "%s", not "%s".', $handlerId, $module, $expected, $transport));
        }
    }
}
