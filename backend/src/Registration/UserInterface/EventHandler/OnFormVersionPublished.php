<?php

declare(strict_types=1);

namespace Eventor\Registration\UserInterface\EventHandler;

use Eventor\Catalog\Contract\Event\FormVersionPublished;
use Eventor\Registration\Application\Answers\RequestMissingAnswers;
use Eventor\Shared\Application\Audit\AuditContext;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Audit\AuditActor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * `catalog.form_version_published` (S17): only a version that adds required fields can make answers incomplete.
 */
#[AsMessageHandler(fromTransport: 'registration_events')]
final readonly class OnFormVersionPublished
{
    public function __construct(
        private CommandBus $commands,
        private AuditContext $audit,
    ) {}

    public function __invoke(FormVersionPublished $event): void
    {
        if ([] === $event->addedRequiredFieldIds) {
            return;
        }

        $this->audit->as(AuditActor::system('registration.request_missing_answers'), fn(): mixed => $this->commands->dispatch(
            new RequestMissingAnswers($event->organizerId, $event->eventId, $event->formVersionId),
            TenantStamp::of($event->organizerId),
        ));
    }
}
