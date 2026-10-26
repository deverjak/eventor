<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Delivery;

use Eventor\Communication\Application\Sending\DeliveryComposer;
use Eventor\Communication\Application\Sending\MessageNotComposable;
use Eventor\Communication\Application\Sending\RecipientStillEligible;
use Eventor\Communication\Contract\DeliveryState;
use Eventor\Communication\Domain\Delivery\Delivery;
use Eventor\Communication\Domain\Delivery\DeliveryRepository;
use Eventor\Communication\Domain\Sending\MessageSender;
use Eventor\Communication\Domain\Sending\SendFailed;
use Eventor\Shared\Domain\Id\DeliveryId;
use Eventor\Shared\Domain\Messaging\NoDomainEvent;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The heart of the asynchronous e-mail (chapter 06): lock the delivery, leave it alone if it is not waiting any more
 * (a redelivered queue message), check once more that the recipient still meets the condition (COM-02), write the
 * message from the current facts, send it through the sender chain, record the result. An unreachable mail service is
 * an attempt that failed, not a failure of the command: the attempt is stored and `Retry` makes the subscriber ask the
 * queue for another go (1 min, 5 min, 30 min, 2 h); the fifth attempt gives the delivery up (`delivery_failed`).
 */
#[AsMessageHandler(bus: 'command.bus')]
#[NoDomainEvent('A message that is not waiting any more (already sent, skipped) is left alone and records nothing.')]
final readonly class AttemptDeliveryHandler
{
    public function __construct(
        private DeliveryRepository $deliveries,
        private RecipientStillEligible $eligibility,
        private DeliveryComposer $composer,
        private MessageSender $sender,
        private CampaignCompletion $completion,
        private WorkflowInterface $deliveryStateMachine,
        private ClockInterface $clock,
    ) {}

    public function __invoke(AttemptDelivery $command): AttemptOutcome
    {
        $delivery = $this->deliveries->findForUpdate(DeliveryId::fromString($command->deliveryId));
        if (null === $delivery || DeliveryState::Scheduled !== $delivery->getState()) {
            return AttemptOutcome::Ignored;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $outcome = $this->attempt($delivery, $now);
        if (AttemptOutcome::Retry !== $outcome) {
            $this->completion->afterLeavingScheduled($delivery, $now);
        }

        return $outcome;
    }

    private function attempt(Delivery $delivery, \DateTimeImmutable $now): AttemptOutcome
    {
        $eligibility = $this->eligibility->check($delivery);
        if (!$eligibility->eligible) {
            $delivery->skip($this->deliveryStateMachine, $eligibility->reason ?? 'not_eligible', $now);

            return AttemptOutcome::Skipped;
        }

        try {
            $message = $this->composer->compose($delivery);
        } catch (MessageNotComposable $e) {
            $delivery->fail($this->deliveryStateMachine, $e->errorCode, $now);

            return AttemptOutcome::Failed;
        }

        try {
            $result = $this->sender->send($message);
        } catch (SendFailed $e) {
            if ($delivery->recordFailedAttempt($e->errorCode, $now)) {
                $delivery->fail($this->deliveryStateMachine, $e->errorCode, $now);

                return AttemptOutcome::Failed;
            }

            return AttemptOutcome::Retry;
        }

        if (!$result->sent) {
            $delivery->skip($this->deliveryStateMachine, $result->suppressedBecause ?? 'suppressed', $now);

            return AttemptOutcome::Skipped;
        }
        $delivery->markSent($this->deliveryStateMachine, $result->messageId, self::mask($message->toEmail), $now);

        return AttemptOutcome::Sent;
    }

    /**
     * "j***@example.com": enough to recognise the recipient in the history, not enough to contact them.
     */
    public static function mask(string $email): string
    {
        $at = strrpos($email, '@');
        if (false === $at || $at < 1) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }
}
