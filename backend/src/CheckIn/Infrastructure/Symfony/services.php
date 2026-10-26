<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eventor\CheckIn\Application\Query\CheckInDeskQuery;
use Eventor\CheckIn\Application\Query\CheckInOverviewQuery;
use Eventor\CheckIn\Domain\CheckInRuleMatrix;
use Eventor\CheckIn\Domain\Rule\AlreadyCheckedInRule;
use Eventor\CheckIn\Domain\Rule\CancelledOrExpiredRule;
use Eventor\CheckIn\Domain\Rule\IncompleteRegistrationRule;
use Eventor\CheckIn\Domain\Rule\MissingConfirmationRule;
use Eventor\CheckIn\Domain\Rule\RequiresAttentionRule;
use Eventor\CheckIn\Domain\Rule\UnpaidRule;
use Eventor\CheckIn\Domain\Rule\WrongActivityRule;
use Eventor\Shared\Infrastructure\Symfony\ModuleServices;

// Services and framework configuration of the CheckIn module (schema `checkin`).
return static function (ContainerConfigurator $container): void {
    $services = ModuleServices::register($container, 'CheckIn', \dirname(__DIR__, 2));

    // `checkin_events`: the outbox queue of the integration events CheckIn subscribes to.
    ModuleServices::eventTransport($container, 'checkin');

    // The exception matrix (CHK-09). The domain is not scanned for services, so the rules are registered here, tagged,
    // and the tag priority is their severity: the first rule that applies decides (most severe first).
    foreach ([
        AlreadyCheckedInRule::class,
        CancelledOrExpiredRule::class,
        WrongActivityRule::class,
        RequiresAttentionRule::class,
        IncompleteRegistrationRule::class,
        MissingConfirmationRule::class,
        UnpaidRule::class,
    ] as $position => $rule) {
        $services->set($rule)->tag('eventor.check_in_rule', ['priority' => 100 - $position]);
    }
    $services->set(CheckInRuleMatrix::class)->args([tagged_iterator('eventor.check_in_rule')]);

    // The read models of the controllers hold Contract dependencies of other modules (Registration, Payments). A private
    // service that is used once gets inlined into its controller in dev and prod, and ModuleBoundaryPass cannot see
    // the declared Contract type of a nested definition. Public services are never inlined.
    $services->set(CheckInDeskQuery::class)->public();
    $services->set(CheckInOverviewQuery::class)->public();

    $container->extension('framework', [
        // Problem titles of this module (domain `problems`, merged with the shared catalogue).
        'translator' => ['paths' => [\dirname(__DIR__, 2).'/Resources/translations']],
    ]);

    if ('test' === $container->env()) {
        // S10: N operating-system processes check the same participant in at the same moment (tests/Scenario/CheckIn).
        $services->set('Eventor\Tests\Support\CheckIn\CheckInRaceCommand')->autowire()->autoconfigure();

        // Public aliases (`test.<FQCN>`, read by IntegrationTestCase::service()) of the services the tests call directly.
        foreach ([
            \Eventor\CheckIn\Contract\CheckInQuery::class,
            CheckInRuleMatrix::class,
        ] as $class) {
            $services->alias('test.'.$class, $class)->public();
        }
    }
};
