<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Sending;

/**
 * Port of the channel (chapter 07): the e-mail implementation sits on Symfony Mailer. `SuppressingMessageSender`
 * decorates it and refuses what must not go out (withdrawn consent, suppressed address).
 */
interface MessageSender
{
    /**
     * @throws SendFailed
     */
    public function send(OutgoingMessage $message): SendOutcome;
}
