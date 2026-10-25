<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity\Support;

use Eventor\Payments\Contract\BankConnectionView;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\PairrotOnboarding;
use Eventor\Payments\Contract\ReceptionState;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Test double of the Payments facade `PairrotOnboarding`, registered by Identity's services.php in the test
 * environment only. It records the calls; `failWith()` makes the next call throw.
 */
final class FakePairrotOnboarding implements PairrotOnboarding
{
    /** @var list<string> */
    public array $calls = [];

    private ?\Throwable $failure = null;

    public function failWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function activateSubtenant(OrganizerId $organizer, string $organizerName, string $contactEmail): string
    {
        $this->calls[] = 'activateSubtenant:'.$contactEmail;
        $this->failIfRequested();

        return 'eventor_org_'.$organizer->toString();
    }

    public function connectBankAccount(OrganizerId $organizer, string $subtenantId, string $accountNumberOrIban, string $label): BankConnectionView
    {
        $this->calls[] = 'connectBankAccount:'.$subtenantId;
        $this->failIfRequested();
        if ('CZ0000000000000000000000' === $accountNumberOrIban) {
            throw BankAccountUnavailable::alreadyConnected();
        }

        return new BankConnectionView('bc_'.substr(md5($accountNumberOrIban), 0, 8), substr($accountNumberOrIban, 0, 4).'****'.substr($accountNumberOrIban, -4), 'in+'.substr(md5($subtenantId), 0, 6).'@pairrot.test');
    }

    public function receptionState(OrganizerId $organizer, string $subtenantId, string $bankConnectionId): ReceptionState
    {
        $this->calls[] = 'receptionState';

        return ReceptionState::Waiting;
    }

    private function failIfRequested(): void
    {
        if (null !== $this->failure) {
            $failure = $this->failure;
            $this->failure = null;

            throw $failure;
        }
    }
}
