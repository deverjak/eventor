<?php

declare(strict_types=1);

namespace Eventor\Communication\UserInterface\EventHandler;

use Eventor\Communication\Application\Delivery\AttemptDelivery;
use Eventor\Communication\Application\Delivery\AttemptOutcome;
use Eventor\Communication\Application\Scheduling\SendDelivery;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Application\Messaging\Stamp\TenantStamp;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Worker side of `async_mail` (chapter 06, "Odesílání e-mailů a idempotence"). The attempt is a command: its result
 * (the failed attempt, the sent message) is committed either way. When the mail service did not answer, the message
 * goes back to the queue with the backoff of the transport (1 min, 5 min, 30 min, 2 h) and the delivery stays
 * `scheduled`; the fifth failed attempt makes the command give the delivery up instead.
 */
#[AsMessageHandler(fromTransport: 'async_mail')]
final readonly class AttemptDeliverySubscriber
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(SendDelivery $message): void
    {
        $outcome = $this->commands->dispatch(new AttemptDelivery($message->deliveryId), TenantStamp::of(OrganizerId::fromString($message->organizerId)));

        if (AttemptOutcome::Retry === $outcome) {
            throw new RecoverableMessageHandlingException('The mail service did not accept the message; trying again later.');
        }
    }
}
