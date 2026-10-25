<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Audit;

use Doctrine\DBAL\Exception as DbalException;
use Eventor\Shared\Application\Audit\AuditRecord;
use Eventor\Shared\Application\Audit\AuditRecorder;
use Eventor\Shared\Domain\Audit\AuditActor;
use Eventor\Shared\Domain\Audit\AuditChange;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Tests\Support\IntegrationTestCase;

/**
 * AUD-01: an entry written through the real recorder cannot be altered or removed by the application role
 * (`eventor_app`), neither by UPDATE, DELETE nor TRUNCATE. The grants themselves are listed in SchemaGrantsTest;
 * this proves it for an entry written by the application.
 */
final class AuditImmutabilityTest extends IntegrationTestCase
{
    public function test_an_entry_written_by_the_application_cannot_be_updated_deleted_or_truncated(): void
    {
        $organizer = OrganizerId::fromString($this->createOwner('imm@example.test', 'imm-club')['organizerId']);
        $this->service(AuditRecorder::class)->record(new AuditRecord(
            new AuditChange('event', 'ev-1', 'event.created', $organizer),
            AuditActor::system('test'),
            'corr-1',
            new \DateTimeImmutable('2026-11-01T10:00:00+00:00'),
            $organizer,
        ));
        self::assertSame('eventor_app', $this->dbString('SELECT current_user'));

        foreach ([
            "UPDATE audit.audit_entry SET action = 'tampered' WHERE entity_id = 'ev-1'",
            "UPDATE audit.audit_entry SET before = '{}'::jsonb",
            "DELETE FROM audit.audit_entry WHERE entity_id = 'ev-1'",
            'TRUNCATE audit.audit_entry',
        ] as $sql) {
            $this->connection()->createSavepoint('attempt');
            try {
                $this->connection()->executeStatement($sql);
                self::fail("The application role must not be able to run: $sql");
            } catch (DbalException $e) {
                self::assertStringContainsString('permission denied', $e->getMessage(), $sql);
            } finally {
                $this->connection()->rollbackSavepoint('attempt');
            }
        }

        self::assertSame(1, $this->dbInt("SELECT count(*) FROM audit.audit_entry WHERE entity_id = 'ev-1' AND action = 'event.created'"));
    }
}
