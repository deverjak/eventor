<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Architecture;

use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Tests\Support\EventSampler;
use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Contract-first scaffolding made visible: which subscribers are still to be written (`#[PendingSubscribers]`), and the
 * switch that turns the remaining list into a failure.
 *
 *     docker compose exec -e EVENTOR_REPORT_PENDING=1 php vendor/bin/phpunit --filter PendingSubscribersTest   # print the list
 *     docker compose exec -e EVENTOR_STRICT_SUBSCRIBERS=1 ...                                                   # W4: fail while any is left
 *
 * `IntegrationEventRoutingPass` already fails the build when a listed module has a handler, so the list can only be
 * correct or shorter than reality; this test double-checks that against the compiled routing and reports the rest.
 */
final class PendingSubscribersTest extends IntegrationTestCase
{
    public function test_pending_subscribers_name_real_modules_and_none_of_them_is_routed_yet(): void
    {
        $locator = static::getContainer()->get('messenger.senders_locator');
        self::assertInstanceOf(SendersLocatorInterface::class, $locator);

        $modules = array_map('strtolower', array_diff(array_keys(ModuleCatalog::SCHEMAS), ['Shared']));
        $pending = self::pending();

        foreach ($pending as $event => $listed) {
            $routedTransports = array_keys(iterator_to_array($locator->getSenders(new Envelope(EventSampler::sample($event)))));
            foreach ($listed as $module) {
                self::assertContains($module, $modules, "$event lists the unknown module $module");
                self::assertNotContains($module.'_events', $routedTransports, "$event: $module already subscribes, drop it from #[PendingSubscribers]");
            }
        }

        if (false !== getenv('EVENTOR_REPORT_PENDING')) {
            fwrite(\STDERR, "\n".self::report($pending)."\n");
        }
    }

    public function test_no_pending_subscribers_remain(): void
    {
        if ('1' !== getenv('EVENTOR_STRICT_SUBSCRIBERS')) {
            self::markTestSkipped('Contract-first phase: subscribers are still being written. W4 sets EVENTOR_STRICT_SUBSCRIBERS=1 to require none left.');
        }

        self::assertSame([], self::pending(), "Subscribers still pending:\n".self::report(self::pending()));
    }

    /**
     * @return array<class-string, list<string>> event => modules that still have to subscribe
     */
    private static function pending(): array
    {
        $pending = [];
        foreach (ModuleCatalog::contractEvents() as ['class' => $class]) {
            $attributes = (new \ReflectionClass($class))->getAttributes(PendingSubscribers::class);
            if ([] !== $attributes) {
                $pending[$class] = $attributes[0]->newInstance()->modules;
            }
        }

        return $pending;
    }

    /**
     * @param array<class-string, list<string>> $pending
     */
    private static function report(array $pending): string
    {
        $perModule = [];
        foreach ($pending as $class => $modules) {
            $name = (new \ReflectionClass($class))->getAttributes(IntegrationEvent::class)[0]->newInstance()->name;
            foreach ($modules as $module) {
                $perModule[$module][] = $name;
            }
        }
        ksort($perModule);

        $lines = [\sprintf('Pending subscribers: %d (event, module) pairs in %d modules', array_sum(array_map('count', $perModule)), \count($perModule))];
        foreach ($perModule as $module => $events) {
            sort($events);
            $lines[] = \sprintf('  %-14s %2d  %s', $module, \count($events), implode(', ', $events));
        }

        return implode("\n", $lines);
    }
}
