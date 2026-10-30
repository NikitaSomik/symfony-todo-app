<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030231239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark the personal workspace of each existing user.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        // The membership of whoever created a workspace is written at the moment the workspace is:
        // a promoted owner joined later. The earliest of those per user is the one from registration.
        $this->addSql(<<<'SQL'
            UPDATE workspaces
            SET personal = true
            WHERE id IN (
                SELECT DISTINCT ON (m.user_id) w.id
                FROM workspace_members m
                JOIN workspaces w ON w.id = m.workspace_id
                WHERE m.role = 'owner' AND m.joined_at = w.created_at
                ORDER BY m.user_id, w.created_at, w.id
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('UPDATE workspaces SET personal = false');
    }
}
