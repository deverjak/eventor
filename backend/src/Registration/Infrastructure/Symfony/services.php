<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Registration\Application\Capacity\AvailabilityInvalidator;
use Eventor\Registration\Application\Capacity\CapacityReleaser;
use Eventor\Registration\Application\Capacity\Query\CapacityConsistencyCheck;
use Eventor\Registration\Contract\AvailabilityReader;
use Eventor\Registration\Contract\CapacityProvisioner;
use Eventor\Registration\Contract\CapacityQuery;
use Eventor\Registration\Contract\OrderQuery;
use Eventor\Registration\Contract\ParticipationQrTokens;
use Eventor\Registration\Contract\ParticipationQuery;
use Eventor\Registration\Contract\RecipientQuery;
use Eventor\Registration\Contract\SelfServiceLinks;
use Eventor\Registration\Domain\Answer\AnswerSetRepository;
use Eventor\Registration\Domain\Capacity\CapacityAllocator;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Registration\Infrastructure\Doctrine\AnswerSetIdType;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the Registration module (schema `registration`): the `participation` state
// machine (chapter 05), the rate limiters of the public order endpoint (API-03), the availability cache pool, the
// inbound transport `registration_events`, the scheduler of the reservation sweeper and the problem titles.
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Registration', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'registration');
    ModuleServices::dbalTypes($container, [AnswerSetIdType::NAME => AnswerSetIdType::class]);

    // The order endpoint limits per client address (book: 10 per 10 minutes) and per address and event (3 per minute).
    // The black-box API stack raises both through its environment (ORDER_MAX_PER_10_MIN, ORDER_MAX_PER_MIN_EVENT).
    $parameters = $container->parameters();
    $parameters->set('env(ORDER_MAX_PER_10_MIN)', '10');
    $parameters->set('env(ORDER_MAX_PER_MIN_EVENT)', '3');
    $parameters->set('env(RECOVER_MAX_PER_HOUR_IP)', '10');
    $parameters->set('env(RECOVER_MAX_PER_HOUR_EMAIL)', '3');

    // Public availability cache (CachedAvailabilityReader): tag-aware, shared between the php and worker containers
    // through Postgres (shared.cache_items), because the worker invalidates what the web process cached. Tests use
    // an in-process array pool.
    $container->extension('framework', [
        'cache' => ['pools' => ['cache.availability' => 'test' === $container->env()
            ? ['adapter' => 'cache.adapter.array', 'provider' => null, 'tags' => true, 'default_lifetime' => 60]
            : ['adapter' => 'cache.adapter.doctrine_dbal', 'provider' => 'doctrine.dbal.default_connection', 'tags' => true, 'default_lifetime' => 60],
        ]],
        'rate_limiter' => [
            'registration_order_ip' => ['policy' => 'sliding_window', 'limit' => '%env(int:ORDER_MAX_PER_10_MIN)%', 'interval' => '10 minutes', 'cache_pool' => 'cache.rate_limiter'],
            'registration_order_event' => ['policy' => 'sliding_window', 'limit' => '%env(int:ORDER_MAX_PER_MIN_EVENT)%', 'interval' => '1 minute', 'cache_pool' => 'cache.rate_limiter'],
            'registration_recover_ip' => ['policy' => 'sliding_window', 'limit' => '%env(int:RECOVER_MAX_PER_HOUR_IP)%', 'interval' => '1 hour', 'cache_pool' => 'cache.rate_limiter'],
            'registration_recover_email' => ['policy' => 'sliding_window', 'limit' => '%env(int:RECOVER_MAX_PER_HOUR_EMAIL)%', 'interval' => '1 hour', 'cache_pool' => 'cache.rate_limiter'],
        ],
        // Titles of the problem codes of this module (the shared catalogue stays untouched).
        'translator' => ['paths' => [\dirname(__DIR__).'/Translations']],
        'workflows' => [
            // Chapter 05: pending -> confirmed | expired | cancelled; expired -> requires_attention | confirmed (restore);
            // requires_attention -> confirmed | cancelled. Two starting points (pending / confirmed at once for cash and
            // free orders) are the creation of the entity. Guards: Infrastructure/Workflow/ParticipationGuard.
            'participation' => [
                'type' => 'state_machine',
                'audit_trail' => ['enabled' => false],
                'marking_store' => ['type' => 'method', 'property' => 'state'],
                'supports' => [Participation::class],
                'initial_marking' => 'pending',
                'places' => ['pending', 'confirmed', 'expired', 'requires_attention', 'cancelled', 'transferred'],
                'transitions' => [
                    'confirm' => ['from' => 'pending', 'to' => 'confirmed'],
                    'confirm_on_site' => ['from' => 'pending', 'to' => 'confirmed'],
                    'expire' => ['from' => 'pending', 'to' => 'expired'],
                    'restore' => ['from' => ['expired', 'requires_attention'], 'to' => 'confirmed'],
                    'flag_late_payment' => ['from' => 'expired', 'to' => 'requires_attention'],
                    'cancel' => ['from' => ['pending', 'confirmed', 'requires_attention'], 'to' => 'cancelled'],
                    'transfer' => ['from' => 'confirmed', 'to' => 'transferred'],
                ],
            ],
        ],
    ]);

    // Test environment only: public aliases (`test.<id>`) of the facades and of the state machine, for the tests that
    // call them the way another module does (IntegrationTestCase::service()), and the capacity-race command.
    if ('test' === $container->env()) {
        foreach ([
            CapacityAllocator::class, CapacityProvisioner::class, CapacityQuery::class, AvailabilityReader::class, AvailabilityInvalidator::class,
            CapacityReleaser::class, CapacityConsistencyCheck::class,
            ParticipationQuery::class, OrderQuery::class, RecipientQuery::class, SelfServiceLinks::class, ParticipationQrTokens::class,
            AnswerSetRepository::class,
        ] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
        $services->alias('test.cache.availability', 'cache.availability')->public();
        $services->alias('test.workflow.participation', 'state_machine.participation')->public();
        $services->set('Eventor\Tests\Support\Concurrency\ClaimSeatsCommand')->autowire()->autoconfigure();
    }
};
