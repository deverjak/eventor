<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Identity;

use Eventor\Identity\Application\Access\OrganizerPolicy;
use Eventor\Identity\Application\Access\PermissionMatrix;
use Eventor\Identity\Contract\Permission;
use Eventor\Identity\Domain\Membership\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The matrix of docs/initial-idea/zadani-event-platforma.md section 3.2, row by row. This table is deliberately a
 * second, independent transcription of the specification: if PermissionMatrix drifts, this test fails.
 */
final class PermissionMatrixTest extends TestCase
{
    private const string POLICY = 'policy';

    /**
     * permission => [Vlastník, Správce akce, Finance, Odbavení]; true, false or 'policy' (✔ only if the organizer allows it).
     *
     * @var array<string, array{bool|string, bool|string, bool|string, bool|string}>
     */
    private const array SPEC = [
        'organizer.settings.manage' => [true, false, false, false],   // Nastavení organizace, bankovní účet, Pairrot
        'team.manage' => [true, false, false, false],                 // Správa týmu a rolí
        'legal.organizer.publish' => [true, false, false, false],     // Právní dokumenty organizátora (nová verze)
        'event.edit' => [true, true, false, false],                   // Vytvořit / upravit / publikovat událost
        'legal.event.manage' => [true, true, false, false],           // Právní dokumenty události / aktivity
        'event.configure' => [true, true, false, false],              // Konfigurace formuláře, aktivit, kapacit, cen
        'participants.view.full' => [true, true, true, false],        // Zobrazit účastníky (plné osobní údaje)
        'participants.view.checkin' => [true, true, true, true],      // Zobrazit účastníky (odbavovací pohled)
        'personal_data.export' => [true, true, false, false],         // Export CSV osobních údajů
        'registration.edit' => [true, true, false, false],            // Upravit registrační údaje, storno, převod
        'payment.exception.assign' => [true, true, true, false],      // Řešit platební výjimky: přiřadit (Správce akce ✔*)
        'payment.exception.resolve' => [true, self::POLICY, true, false], // ... finální rozhodnutí (lze zjednodušit konfigurací)
        'cash.record' => [true, true, true, self::POLICY],            // Evidovat hotovost (odbavení: pokud povoleno)
        'cash.correct' => [true, false, true, false],                 // Opravit hotovostní záznam
        'refund.create' => [true, true, true, false],                 // Refund úkoly: vytvořit
        'refund.close' => [true, false, true, false],                 // Refund úkoly: uzavřít
        'checkin.perform' => [true, true, false, true],               // Odbavit účast
        'checkin.correct' => [true, true, false, false],              // Opravit / zrušit odbavení
        'message.send' => [true, true, false, false],                 // Vytvořit a odeslat zprávu
        'program.manage' => [true, true, false, false],               // Program a aktuality
        'content.manage' => [true, true, false, false],               // Galerie a materiály
        'dsr.manage' => [true, false, false, false],                  // Žádosti subjektů údajů
        'event.cancel' => [true, false, false, false],                // Zrušit událost
    ];

    private const array ROLES = [Role::Owner, Role::EventManager, Role::Finance, Role::CheckIn];

    /**
     * @return iterable<string, array{string, Role, bool|string}>
     */
    public static function cells(): iterable
    {
        foreach (self::SPEC as $permission => $columns) {
            foreach (self::ROLES as $index => $role) {
                yield "$permission / {$role->value}" => [$permission, $role, $columns[$index]];
            }
        }
    }

    #[DataProvider('cells')]
    public function test_every_cell_matches_the_specification(string $permission, Role $role, bool|string $expected): void
    {
        $permission = Permission::from($permission);
        $default = OrganizerPolicy::defaults();
        $allOn = new OrganizerPolicy(checkInMayRecordCash: true, eventManagerMayResolvePayments: true);

        if (self::POLICY === $expected) {
            self::assertFalse(PermissionMatrix::allows($role, $permission, $default), 'conditional cells are off by default');
            self::assertTrue(PermissionMatrix::allows($role, $permission, $allOn), 'conditional cells follow the organizer policy');

            return;
        }

        self::assertSame($expected, PermissionMatrix::allows($role, $permission, $default));
        self::assertSame($expected, PermissionMatrix::allows($role, $permission, $allOn));
    }

    public function test_audit_log_visibility_is_graded_by_role(): void
    {
        $policy = OrganizerPolicy::defaults();

        self::assertTrue(PermissionMatrix::allows(Role::Owner, Permission::AuditViewAll, $policy));
        self::assertTrue(PermissionMatrix::allows(Role::EventManager, Permission::AuditViewOwnEvents, $policy));
        self::assertTrue(PermissionMatrix::allows(Role::Finance, Permission::AuditViewFinance, $policy));

        self::assertFalse(PermissionMatrix::allows(Role::EventManager, Permission::AuditViewAll, $policy));
        self::assertFalse(PermissionMatrix::allows(Role::Finance, Permission::AuditViewAll, $policy));
        foreach ([Permission::AuditViewAll, Permission::AuditViewOwnEvents, Permission::AuditViewFinance] as $audit) {
            self::assertFalse(PermissionMatrix::allows(Role::CheckIn, $audit, $policy), 'check-in staff never sees the audit log');
        }
    }

    public function test_every_member_can_see_their_organizer(): void
    {
        foreach (self::ROLES as $role) {
            self::assertTrue(PermissionMatrix::allows($role, Permission::OrganizerView, OrganizerPolicy::defaults()));
        }
    }

    public function test_no_permission_exists_outside_the_specification(): void
    {
        $known = [...array_keys(self::SPEC), 'organizer.view', 'audit.view.all', 'audit.view.own_events', 'audit.view.finance'];
        $actual = array_map(static fn(Permission $permission): string => $permission->value, Permission::cases());

        self::assertEqualsCanonicalizing($known, $actual);
    }

    public function test_the_owner_holds_every_permission(): void
    {
        $owned = array_map(static fn(Permission $p): string => $p->value, PermissionMatrix::granted(Role::Owner));
        $all = array_map(static fn(Permission $p): string => $p->value, Permission::cases());

        // The owner sees the audit log through audit.view.all only.
        self::assertEqualsCanonicalizing(array_values(array_diff($all, ['audit.view.own_events', 'audit.view.finance'])), $owned);
    }
}
