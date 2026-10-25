<?php

declare(strict_types=1);

namespace Eventor\Identity\Application\Access;

use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\Membership\Role;

/**
 * The permission matrix of the specification (section 3.2) as data: one constant, read by the voters, by
 * PermissionMatrixPass and by the unit test that compares it with the specification row by row.
 *
 * Two cells of the specification are conditional on organizer policy (`organizer_policy`, wired by the Identity
 * module in P0 phase F1): "Check-in may record cash" and "Event manager may resolve payment exceptions". They are
 * listed in CONDITIONAL and granted only when the organizer switched the policy on.
 */
final class PermissionMatrix
{
    /** @var array<string, list<Permission>> role value => granted permissions */
    public const array MATRIX = [
        'OWNER' => [
            Permission::OrganizerView,
            Permission::OrganizerSettingsManage,
            Permission::TeamManage,
            Permission::OrganizerLegalPublish,
            Permission::EventEdit,
            Permission::EventLegalManage,
            Permission::EventConfigure,
            Permission::ParticipantsViewFull,
            Permission::ParticipantsViewCheckIn,
            Permission::PersonalDataExport,
            Permission::RegistrationEdit,
            Permission::PaymentExceptionAssign,
            Permission::PaymentExceptionResolve,
            Permission::CashRecord,
            Permission::CashCorrect,
            Permission::RefundCreate,
            Permission::RefundClose,
            Permission::CheckInPerform,
            Permission::CheckInCorrect,
            Permission::MessageSend,
            Permission::ProgramManage,
            Permission::ContentManage,
            Permission::DataSubjectRequestManage,
            Permission::AuditViewAll,
            Permission::EventCancel,
        ],
        'EVENT_MANAGER' => [
            Permission::OrganizerView,
            Permission::EventEdit,
            Permission::EventLegalManage,
            Permission::EventConfigure,
            Permission::ParticipantsViewFull,
            Permission::ParticipantsViewCheckIn,
            Permission::PersonalDataExport,
            Permission::RegistrationEdit,
            Permission::PaymentExceptionAssign,
            Permission::CashRecord,
            Permission::RefundCreate,
            Permission::CheckInPerform,
            Permission::CheckInCorrect,
            Permission::MessageSend,
            Permission::ProgramManage,
            Permission::ContentManage,
            Permission::AuditViewOwnEvents,
        ],
        'FINANCE' => [
            Permission::OrganizerView,
            Permission::ParticipantsViewFull,
            Permission::ParticipantsViewCheckIn,
            Permission::PaymentExceptionAssign,
            Permission::PaymentExceptionResolve,
            Permission::CashRecord,
            Permission::CashCorrect,
            Permission::RefundCreate,
            Permission::RefundClose,
            Permission::AuditViewFinance,
        ],
        'CHECK_IN' => [
            Permission::OrganizerView,
            Permission::ParticipantsViewCheckIn,
            Permission::CheckInPerform,
        ],
    ];

    /**
     * Cells that are granted only when the organizer enables the named policy flag.
     *
     * @var array<string, array<string, string>> role value => permission value => OrganizerPolicy flag
     */
    public const array CONDITIONAL = [
        'EVENT_MANAGER' => [
            'payment.exception.resolve' => 'eventManagerMayResolvePayments',
        ],
        'CHECK_IN' => [
            'cash.record' => 'checkInMayRecordCash',
        ],
    ];

    public static function allows(Role $role, Permission $permission, OrganizerPolicy $policy): bool
    {
        if (\in_array($permission, self::MATRIX[$role->value] ?? [], true)) {
            return true;
        }

        $flag = self::CONDITIONAL[$role->value][$permission->value] ?? null;

        return null !== $flag && $policy->isEnabled($flag);
    }

    /**
     * A check-in device acts with the rights of the check-in role (ADR 006), policy exceptions included.
     */
    public static function allowsDevice(Permission $permission, OrganizerPolicy $policy): bool
    {
        return self::allows(Role::CheckIn, $permission, $policy);
    }

    /**
     * True when the role holds the permission only if the organizer policy switches it on (so the policy is worth loading).
     */
    public static function dependsOnPolicy(Role $role, Permission $permission): bool
    {
        return !\in_array($permission, self::MATRIX[$role->value] ?? [], true)
            && isset(self::CONDITIONAL[$role->value][$permission->value]);
    }

    /**
     * @return list<Permission>
     */
    public static function granted(Role $role): array
    {
        return self::MATRIX[$role->value] ?? [];
    }

    /**
     * Every permission that at least one role holds unconditionally or conditionally.
     *
     * @return list<Permission>
     */
    public static function coveredPermissions(): array
    {
        $covered = [];
        foreach (self::MATRIX as $permissions) {
            foreach ($permissions as $permission) {
                $covered[$permission->value] = $permission;
            }
        }
        foreach (self::CONDITIONAL as $cells) {
            foreach (array_keys($cells) as $value) {
                $covered[$value] ??= Permission::from($value);
            }
        }

        return array_values($covered);
    }
}
