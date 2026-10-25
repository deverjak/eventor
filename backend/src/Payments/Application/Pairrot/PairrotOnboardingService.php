<?php

declare(strict_types=1);

namespace Eventor\Payments\Application\Pairrot;

use Eventor\Payments\Application\Port\Exception\PaymentGatewayRejected;
use Eventor\Payments\Application\Port\Exception\PaymentGatewayUnavailable;
use Eventor\Payments\Application\Port\PairrotAccounts;
use Eventor\Payments\Application\Port\PaymentGateway;
use Eventor\Payments\Contract\BankConnectionView;
use Eventor\Payments\Contract\Exception\InvalidBankAccount;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Payments\Contract\PairrotOnboarding;
use Eventor\Payments\Contract\ReceptionState;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Pairrot calls of the onboarding wizard (ORG-02, chapter 07): subtenant `<prefix>eventor_org_<uuid>`, bank
 * connection, inbound address, reception state. Every step is idempotent (the wizard can be interrupted and
 * repeated) and every Pairrot failure becomes a Contract exception. The ids are mirrored in
 * `organizer_pairrot_account` so webhooks can be traced to the organizer.
 */
#[AsAlias(PairrotOnboarding::class)]
final readonly class PairrotOnboardingService implements PairrotOnboarding
{
    public function __construct(
        private PaymentGateway $gateway,
        private PairrotAccounts $accounts,
        #[Autowire('%env(PAIRROT_SUBTENANT_PREFIX)%')]
        private string $subtenantPrefix,
    ) {}

    public function activateSubtenant(OrganizerId $organizer, string $organizerName, string $contactEmail): string
    {
        $existing = $this->accounts->find($organizer);
        if (null !== $existing) {
            return $existing->subtenantId;
        }

        $externalId = $this->subtenantPrefix.'eventor_org_'.$organizer->toString();
        try {
            $subtenant = $this->gateway->createSubtenant($externalId, $organizerName, $contactEmail);
        } catch (PaymentGatewayUnavailable $e) {
            throw PairrotUnavailable::because($e->errorCode());
        } catch (PaymentGatewayRejected $e) {
            throw PairrotUnavailable::because($e->errorCode());
        }
        $this->accounts->saveSubtenant($organizer, $subtenant->id, $externalId);

        return $subtenant->id;
    }

    public function connectBankAccount(OrganizerId $organizer, string $subtenantId, string $accountNumberOrIban, string $label): BankConnectionView
    {
        try {
            $connection = $this->gateway->connectBankAccount($subtenantId, $accountNumberOrIban, $label);
            $inbound = $connection->inboundAddress ?? $this->gateway->createInboundAddress($subtenantId, $connection->id);
        } catch (PaymentGatewayUnavailable $e) {
            throw PairrotUnavailable::because($e->errorCode());
        } catch (PaymentGatewayRejected $e) {
            if (400 === $e->status || 422 === $e->status) {
                throw InvalidBankAccount::because($e->errorCode());
            }
            throw PairrotUnavailable::because($e->errorCode());
        }

        $masked = self::mask($connection->iban);
        $this->accounts->saveConnection($organizer, $connection->id, $masked, $inbound);

        return new BankConnectionView($connection->id, $masked, $inbound);
    }

    public function receptionState(OrganizerId $organizer, string $subtenantId, string $bankConnectionId): ReceptionState
    {
        try {
            return $this->gateway->receptionState($subtenantId, $bankConnectionId);
        } catch (PaymentGatewayUnavailable|PaymentGatewayRejected $e) {
            throw PairrotUnavailable::because($e->errorCode());
        }
    }

    /** `CZ65 **** **** **** 5399`: the bank and the last four digits are enough to recognise the account. */
    public static function mask(string $iban): string
    {
        $compact = preg_replace('/\s+/', '', $iban) ?? $iban;
        if (\strlen($compact) <= 8) {
            return $compact;
        }

        return substr($compact, 0, 4).' '.str_repeat('*', \strlen($compact) - 8).' '.substr($compact, -4);
    }
}
