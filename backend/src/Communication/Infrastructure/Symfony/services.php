<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\CheckIn\Contract\CheckInQuery;
use Eventor\Communication\Application\Audience\CampaignRecipients;
use Eventor\Communication\Application\Query\CampaignComposerQuery;
use Eventor\Communication\Application\Scheduling\SendDelivery;
use Eventor\Communication\Domain\Campaign\Campaign;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Infrastructure\Doctrine\MessageTemplateIdType;
use Eventor\Communication\Infrastructure\Doctrine\SuppressionIdType;
use Eventor\Communication\Infrastructure\Mail\RecordingMailer;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the Communication module (schema `communication`): the inbound transport
// `communication_events`, the mail queue `async_mail`, the two state machines of chapter 05 (campaign, delivery), the
// problem titles, and the test wiring (recording mailer, public aliases).
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'Communication', \dirname(__DIR__, 2));
    $services->set(CampaignRecipients::class)->arg('$checkIns', service(CheckInQuery::class));
    // ModuleServices deliberately scans the application layer for handlers and queries. Messages and read DTOs are
    // values passed to those services, not services themselves; make that distinction explicit so scalar payloads are
    // never autowired while compiling the container.
    foreach ([
        'Eventor\\Communication\\Application\\Campaign\\CancelCampaign',
        'Eventor\\Communication\\Application\\Campaign\\CreateCampaign',
        'Eventor\\Communication\\Application\\Campaign\\ProposeCampaign',
        'Eventor\\Communication\\Application\\Campaign\\ScheduleCampaign',
        'Eventor\\Communication\\Application\\Campaign\\StartCampaign',
        'Eventor\\Communication\\Application\\Campaign\\UpdateCampaign',
        'Eventor\\Communication\\Application\\Delivery\\AttemptDelivery',
        'Eventor\\Communication\\Application\\Delivery\\AttemptOutcome',
        'Eventor\\Communication\\Application\\Delivery\\ExcludeFromMarketing',
        'Eventor\\Communication\\Application\\Delivery\\RetryFailedDeliveries',
        'Eventor\\Communication\\Application\\Reminder\\ChangeReminderSettings',
        'Eventor\\Communication\\Application\\Reminder\\PlanPaymentReminders',
        'Eventor\\Communication\\Application\\Reminder\\PlanPreEventReminders',
        'Eventor\\Communication\\Application\\Reminder\\ReplanPreEventReminders',
        'Eventor\\Communication\\Application\\Scheduling\\DispatchDelivery',
        'Eventor\\Communication\\Application\\Scheduling\\SendDelivery',
        'Eventor\\Communication\\Application\\Scheduling\\ScheduleDelivery',
        'Eventor\\Communication\\Application\\Scheduling\\SweepCommunication',
        'Eventor\\Communication\\Application\\Suppression\\AddSuppression',
        'Eventor\\Communication\\Application\\Suppression\\LiftSuppression',
        'Eventor\\Communication\\Application\\Template\\ChangeMessageTemplate',
        'Eventor\\Communication\\Application\\Template\\CreateMessageTemplate',
        'Eventor\\Communication\\Application\\Template\\RemoveMessageTemplate',
        'Eventor\\Communication\\Application\\Sending\\Eligibility',
        'Eventor\\Communication\\Application\\Sending\\MessageContext',
        'Eventor\\Communication\\Application\\Sending\\MessageNotComposable',
        'Eventor\\Communication\\Application\\Rendering\\ResolvedTemplate',
        'Eventor\\Communication\\Application\\Query\\View\\CampaignDetailView',
        'Eventor\\Communication\\Application\\Query\\View\\CampaignPage',
        'Eventor\\Communication\\Application\\Query\\View\\CampaignPreviewView',
        'Eventor\\Communication\\Application\\Query\\View\\CampaignView',
        'Eventor\\Communication\\Application\\Query\\View\\DeliveryPage',
        'Eventor\\Communication\\Application\\Query\\View\\DeliveryView',
        'Eventor\\Communication\\Application\\Query\\View\\ReminderSettingsView',
        'Eventor\\Communication\\Application\\Query\\View\\SuppressionView',
        'Eventor\\Communication\\Application\\Query\\View\\TemplatePreviewView',
        'Eventor\\Communication\\Application\\Query\\View\\TemplateView',
    ] as $value) {
        $services->set($value)->abstract();
    }
    ModuleServices::eventTransport($container, 'communication');
    ModuleServices::dbalTypes($container, [
        MessageTemplateIdType::NAME => MessageTemplateIdType::class,
        SuppressionIdType::NAME => SuppressionIdType::class,
    ]);

    // The technical sender of the platform (COM-05); the organizer's name is added to it, Reply-To is the organizer's contact.
    $container->parameters()->set('env(COMMUNICATION_FROM_ADDRESS)', 'noreply@mail.eventor.cz');

    $mailRetry = ['max_retries' => 4, 'delay' => 60_000, 'multiplier' => 5, 'max_delay' => 7_200_000, 'jitter' => 0.1];

    $container->extension('framework', [
        'translator' => ['paths' => [\dirname(__DIR__, 2).'/Resources/translations']],
        'messenger' => [
            // Chapter 06: sending is a message per delivery on `async_mail` (1 min, 5 min, 25 min, ~2 h; five attempts in all).
            'transports' => ['async_mail' => 'test' === $container->env()
                ? ['dsn' => 'in-memory://?serialize=true', 'retry_strategy' => $mailRetry]
                : ['dsn' => 'doctrine://default?queue_name=async_mail', 'options' => ['redeliver_timeout' => 300, 'auto_setup' => false], 'retry_strategy' => $mailRetry],
            ],
            'routing' => [SendDelivery::class => 'async_mail'],
        ],
        'workflows' => [
            // draft -> scheduled -> sending -> sent; a draft or scheduled campaign can be cancelled.
            'campaign' => [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'state'],
                'supports' => [Campaign::class],
                'initial_marking' => 'draft',
                'places' => ['draft', 'scheduled', 'sending', 'sent', 'cancelled'],
                'transitions' => [
                    'schedule' => ['from' => ['draft', 'scheduled'], 'to' => 'scheduled'],
                    'start' => ['from' => ['draft', 'scheduled'], 'to' => 'sending'],
                    'complete' => ['from' => 'sending', 'to' => 'sent'],
                    'cancel' => ['from' => ['draft', 'scheduled'], 'to' => 'cancelled'],
                ],
            ],
            // Chapter 05, "Doručení zprávy" (COM-06).
            'delivery' => [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'state'],
                'supports' => [Delivery::class],
                'initial_marking' => 'scheduled',
                'places' => ['scheduled', 'sent', 'delivered', 'skipped', 'failed'],
                'transitions' => [
                    'send' => ['from' => 'scheduled', 'to' => 'sent'],
                    'skip' => ['from' => 'scheduled', 'to' => 'skipped'],
                    'deliver' => ['from' => 'sent', 'to' => 'delivered'],
                    'fail' => ['from' => ['scheduled', 'sent'], 'to' => 'failed'],
                    'retry' => ['from' => 'failed', 'to' => 'scheduled'],
                ],
            ],
        ],
    ]);

    if ('test' === $container->env()) {
        // chapter 09: the mailer is recorded in tests and can simulate an outage (S09).
        $services->set(RecordingMailer::class)->decorate('mailer.mailer')->args([service('.inner')])->public();
        $services->alias('test.communication_event_bus', 'event.bus')->public();
        foreach ([\Eventor\Communication\UserInterface\EventHandler\SweepCommunicationSubscriber::class, CampaignComposerQuery::class, \Eventor\Communication\Application\Query\CampaignQuery::class, \Eventor\Communication\Application\Query\DeliveryQuery::class] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
    }
};
