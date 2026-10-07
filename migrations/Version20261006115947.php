<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006115947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Put every task into a workspace.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );
        // Existing tasks belonged to a user and have no workspace to go to, so the column
        // cannot be added to a table that already holds tasks.
        $this->abortIf(
            (bool) $this->connection->fetchOne('SELECT EXISTS (SELECT 1 FROM tasks)'),
            'The tasks table is not empty. Recreate the development database with "make db-fresh".',
        );

        $this->addSql('DROP INDEX idx_tasks_creator_id');
        $this->addSql('ALTER TABLE tasks ADD workspace_id UUID NOT NULL');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_tasks_workspace_id ON tasks (workspace_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('ALTER TABLE tasks DROP CONSTRAINT fk_tasks_workspace');
        $this->addSql('DROP INDEX idx_tasks_workspace_id');
        $this->addSql('ALTER TABLE tasks DROP workspace_id');
        $this->addSql('CREATE INDEX idx_tasks_creator_id ON tasks (creator_id)');
    }
}
