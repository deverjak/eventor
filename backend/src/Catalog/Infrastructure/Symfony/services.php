<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\Catalog\Application\Query\PublicAvailabilityQuery;
use Eventor\Catalog\Application\Query\PublicEventQuery;
use Eventor\Catalog\Contract\EventCatalog;
use Eventor\Catalog\Contract\FormSchemaProvider;
use Eventor\Catalog\Contract\FormValidator;
use Eventor\Catalog\Contract\NewsReader;
use Eventor\Catalog\Contract\ProgramReader;
use Eventor\Catalog\Domain\Structure\Activity;
use Eventor\Catalog\Domain\Structure\Event;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the Catalog module: the two state machines of chapter 05 (publication of an
// event, registration window of an event and of an activity), the problem titles of the module and the inbound
// transport `catalog_events`.
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Catalog', \dirname(__DIR__, 2));
    ModuleServices::eventTransport($container, 'catalog');


    // The two public-page queries read Registration's AvailabilityReader through its Contract. Both services stay
    // public (never inlined into the controller) and both hold the reader, so the container keeps it a shared service
    // and ModuleBoundaryPass sees the declared Contract type instead of Registration's internals behind the facade.
    $services->set(PublicEventQuery::class)->public();
    $services->set(PublicAvailabilityQuery::class)->public();

    // Test environment only: public aliases (`test.<id>`) of the facades and the state machines, for the integration
    // tests that call them the way other modules do (IntegrationTestCase::service()).
    if ('test' === $container->env()) {
        foreach ([EventCatalog::class, FormSchemaProvider::class, FormValidator::class, ProgramReader::class, NewsReader::class] as $facade) {
            $services->alias('test.'.$facade, $facade)->public();
        }
        $services->alias('test.workflow.event_publication', 'state_machine.event_publication')->public();
        $services->alias('test.workflow.registration_window', 'state_machine.registration_window')->public();
    }

    $container->extension('framework', [
        'workflows' => [
            // EVT-03: draft -> published -> (cancelled | completed -> archived). Guards: Infrastructure/Workflow.
            'event_publication' => [
                'type' => 'state_machine',
                'audit_trail' => ['enabled' => false],
                'marking_store' => ['type' => 'method', 'property' => 'publicationState'],
                'supports' => [Event::class],
                'initial_marking' => 'draft',
                'places' => ['draft', 'published', 'completed', 'cancelled', 'archived'],
                'transitions' => [
                    'publish' => ['from' => 'draft', 'to' => 'published'],
                    'cancel' => ['from' => 'published', 'to' => 'cancelled'],
                    'complete' => ['from' => 'published', 'to' => 'completed'],
                    'archive' => ['from' => ['completed', 'cancelled'], 'to' => 'archived'],
                ],
            ],
            // EVT-04: one definition for the event and for each of its activities.
            'registration_window' => [
                'type' => 'state_machine',
                'audit_trail' => ['enabled' => false],
                'marking_store' => ['type' => 'method', 'property' => 'registrationState'],
                'supports' => [Event::class, Activity::class],
                'initial_marking' => 'not_open',
                'places' => ['not_open', 'open', 'paused', 'closed'],
                'transitions' => [
                    'open' => ['from' => 'not_open', 'to' => 'open'],
                    'pause' => ['from' => 'open', 'to' => 'paused'],
                    'resume' => ['from' => 'paused', 'to' => 'open'],
                    'close' => ['from' => ['not_open', 'open', 'paused'], 'to' => 'closed'],
                ],
            ],
        ],
        // Titles of the problem codes of this module (Shared translations stay untouched).
        'translator' => ['paths' => [\dirname(__DIR__).'/Translations']],
    ]);
};
