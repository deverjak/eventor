<?php

declare(strict_types=1);

namespace Eventor\Tests\Integration\Identity;

use Eventor\Tests\Support\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateOwnerCommandTest extends IntegrationTestCase
{
    public function test_it_creates_organizer_account_and_owner_membership(): void
    {
        $tester = $this->tester();

        $status = $tester->execute([
            '--email' => 'Jana@Example.test',
            '--password' => 'correct-horse-battery',
            '--organizer-name' => 'Klub Plzeň',
            '--slug' => 'klub-plzen',
        ]);

        self::assertSame(0, $status, $tester->getDisplay());
        self::assertStringContainsString('Klub Plzeň', $tester->getDisplay());
        $row = $this->connection()->fetchAssociative(
            'SELECT u.email, u.display_name, o.slug, m.role FROM identity.membership m JOIN identity.user_account u ON u.id = m.user_account_id JOIN identity.organizer o ON o.id = m.organizer_id',
        );
        self::assertSame(['email' => 'jana@example.test', 'display_name' => 'Jana', 'slug' => 'klub-plzen', 'role' => 'OWNER'], $row);
    }

    public function test_the_same_account_can_own_a_second_organizer_and_keeps_its_password(): void
    {
        $this->createOwner('jana@example.test', 'prvni');
        $hashBefore = $this->connection()->fetchOne("SELECT password_hash FROM identity.user_account WHERE email = 'jana@example.test'");

        $status = $this->tester()->execute(['--email' => 'jana@example.test', '--password' => 'another-password-1', '--organizer-name' => 'Druhy', '--slug' => 'druhy']);

        self::assertSame(0, $status);
        self::assertSame(1, $this->dbInt('SELECT count(*) FROM identity.user_account'));
        self::assertSame(2, $this->dbInt('SELECT count(*) FROM identity.membership'));
        self::assertSame($hashBefore, $this->connection()->fetchOne("SELECT password_hash FROM identity.user_account WHERE email = 'jana@example.test'"));
    }

    public function test_a_taken_slug_fails_with_a_clear_message_and_changes_nothing(): void
    {
        $this->createOwner('jana@example.test', 'klub');
        $tester = $this->tester();

        $status = $tester->execute(['--email' => 'petr@example.test', '--password' => 'correct-horse-battery', '--organizer-name' => 'X', '--slug' => 'klub']);

        self::assertSame(1, $status);
        self::assertStringContainsString('already taken', $tester->getDisplay());
        self::assertSame(0, $this->dbInt("SELECT count(*) FROM identity.user_account WHERE email = 'petr@example.test'"));
    }

    public function test_invalid_input_is_reported_per_field(): void
    {
        $tester = $this->tester();

        $status = $tester->execute(['--email' => 'not-an-email', '--password' => 'short', '--organizer-name' => 'X', '--slug' => 'Bad Slug!']);

        self::assertSame(2, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString('email', $display);
        self::assertStringContainsString('password', $display);
        self::assertStringContainsString('slug', $display);
        self::assertSame(0, $this->dbInt('SELECT count(*) FROM identity.organizer'));
    }

    public function test_missing_options_are_refused(): void
    {
        $tester = $this->tester();

        self::assertSame(2, $tester->execute(['--email' => 'a@example.test']));
        self::assertStringContainsString('--password option is required', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $kernel = static::$kernel;
        \assert(null !== $kernel);
        $application = new Application($kernel);

        return new CommandTester($application->find('eventor:identity:create-owner'));
    }
}
