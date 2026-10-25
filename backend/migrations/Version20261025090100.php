<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Identity: organizers (tenants), team accounts and memberships.
 */
final class Version20261025090100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: user_account, organizer, membership';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity.user_account (
                id UUID NOT NULL PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(200) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                disabled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT user_account_email_lower CHECK (email = lower(email))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX user_account_email_key ON identity.user_account (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE identity.organizer (
                id UUID NOT NULL PRIMARY KEY,
                slug VARCHAR(64) NOT NULL,
                name VARCHAR(200) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT organizer_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX organizer_slug_key ON identity.organizer (slug)');

        $this->addSql(<<<'SQL'
            CREATE TABLE identity.membership (
                id UUID NOT NULL PRIMARY KEY,
                user_account_id UUID NOT NULL REFERENCES identity.user_account (id) ON DELETE RESTRICT,
                organizer_id UUID NOT NULL REFERENCES identity.organizer (id) ON DELETE RESTRICT,
                role VARCHAR(32) NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                CONSTRAINT membership_role_check CHECK (role IN ('OWNER', 'EVENT_MANAGER', 'FINANCE', 'CHECK_IN'))
            )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX membership_account_organizer_key ON identity.membership (user_account_id, organizer_id)');
        $this->addSql('CREATE INDEX membership_organizer_idx ON identity.membership (organizer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE identity.membership');
        $this->addSql('DROP TABLE identity.organizer');
        $this->addSql('DROP TABLE identity.user_account');
    }
}
