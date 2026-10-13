<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013191225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index the tasks of a workspace by assignee, in place of the index by workspace alone.';
    }

    // CONCURRENTLY keeps tasks writable while the index is built, and cannot run in a transaction.
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('CREATE INDEX CONCURRENTLY idx_tasks_workspace_id_assignee_id ON tasks (workspace_id, assignee_id)');
        $this->addSql('DROP INDEX CONCURRENTLY idx_tasks_workspace_id');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('CREATE INDEX CONCURRENTLY idx_tasks_workspace_id ON tasks (workspace_id)');
        $this->addSql('DROP INDEX CONCURRENTLY idx_tasks_workspace_id_assignee_id');
    }
}
