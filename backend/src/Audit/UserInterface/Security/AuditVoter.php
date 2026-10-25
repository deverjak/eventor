<?php

declare(strict_types=1);

namespace Eventor\Audit\UserInterface\Security;

use Eventor\Identity\Contract\AccessPolicy;
use Eventor\Identity\Contract\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may read which part of the audit log (AUD-04, chapter 8). The permission matrix has three audit permissions:
 *  - `audit.view.all` (owner): the whole organizer log and every entity;
 *  - `audit.view.finance` (finance): entities of the money domain (payments, ledger, refunds, cash);
 *  - `audit.view.own_events` (event manager): every entity outside the money domain. The matrix limits this
 *    role to its own events, but audit entries carry no event id, so the narrowing to the events of the
 *    membership is not applied here (see the module report).
 * The organizer-wide log (no entity type) is for `audit.view.all` only.
 *
 * @extends Voter<string, AuditScope>
 */
final class AuditVoter extends Voter
{
    /** Entity types (by prefix) of the money domain. */
    private const array FINANCE_PREFIXES = ['payment', 'ledger', 'refund', 'order_finance', 'bank_transaction', 'cash', 'pairrot'];

    public function __construct(private readonly AccessPolicy $policy) {}

    public static function isFinanceEntity(string $entityType): bool
    {
        foreach (self::FINANCE_PREFIXES as $prefix) {
            if (str_starts_with($entityType, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return AuditScope::ATTRIBUTE === $attribute && $subject instanceof AuditScope;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        \assert($subject instanceof AuditScope);
        $user = $token->getUser();
        if (null === $user) {
            return false;
        }

        $can = fn(Permission $permission): bool => $this->policy->isAllowed($permission, $subject->organizer, $user->getUserIdentifier());

        if ($can(Permission::AuditViewAll)) {
            return true;
        }
        if (null === $subject->entityType) {
            return false;
        }

        return self::isFinanceEntity($subject->entityType)
            ? $can(Permission::AuditViewFinance)
            : $can(Permission::AuditViewOwnEvents);
    }
}
